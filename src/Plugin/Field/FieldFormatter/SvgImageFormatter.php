<?php

namespace Drupal\svg_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Cache\Cache;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * We have to fully override standard field formatter, so we will keep original
 * label and formatter ID.
 *
 * @FieldFormatter(
 *   id = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class SvgImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\file\Entity\File[] $files */
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $imageLinkSetting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($imageLinkSetting === 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($imageLinkSetting === 'file') {
      $linkFile = TRUE;
    }

    $imageStyleSetting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cacheTags = [];
    if (!empty($imageStyleSetting)) {
      $imageStyle = $this->imageStyleStorage->load($imageStyleSetting);
      $cacheTags = $imageStyle->getCacheTags();
    }

    $svg_attributes = $this->getSetting('svg_attributes');
    foreach ($svg_attributes as &$attribute) {
      if ($attribute) {
        $attribute .= 'px';
      }
    }
    foreach ($files as $delta => $file) {
      $attributes = [];
      $isSvg = svg_image_is_file_svg($file);

      if ($isSvg) {
        $attributes = $svg_attributes;
      }

      $cacheContexts = [];
      if (isset($linkFile)) {
        $imageUri = $file->getFileUri();
        $url = Url::fromUri(file_create_url($imageUri));
        $cacheContexts[] = 'url.site';
      }
      $cacheTags = Cache::mergeTags($cacheTags, $file->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $file->_referringItem;

      if (isset($item->_attributes)) {
        $attributes += $item->_attributes;
      }

      unset($item->_attributes);
      $isSvg = svg_image_is_file_svg($file);

      if (empty($url)) {
        $url = $file->getFileUri();
      }

      if (!$isSvg /*|| $this->getSetting('svg_render_as_image')*/) {
        $elements[$delta] = [
          '#theme' => 'image_formatter',
          '#item' => $item,
          '#item_attributes' => $attributes,
          '#image_style' => $isSvg ? NULL : $imageStyleSetting,
          '#url' => $url,
          '#cache' => [
            'tags' => $cacheTags,
            'contexts' => $cacheContexts,
          ],
        ];
      }

      else {
        // Render as SVG tag.
        $svgRaw = file_get_contents($url);
        $svgRaw = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $svgRaw);
        $svgRaw = trim($svgRaw);

        $elements[$delta] = [
          '#markup' => $svgRaw,
          '#cache' => [
            'tags' => $cacheTags,
            'contexts' => $cacheContexts,
          ],
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'svg_attributes' => ['width' => '', 'height' => ''], 'svg_render_as_image' => TRUE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $element, FormStateInterface $formState) {
    $element = parent::settingsForm($element, $formState);

    $element['svg_render_as_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render SVG image as &lt;img&rt;'),
      '#description' => $this->t('Render SVG images as usual image in IMG tag instead of &lt;svg&rt; tag'),
      '#default_value' => $this->getSetting('svg_render_as_image'),
    ];

    $element['svg_attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('SVG Images dimensions (attributes)'),
      '#tree' => TRUE,
    ];

    $element['svg_attributes']['width'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Width'),
      '#size' => 10,
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('svg_attributes')['width'],
    ];

    $element['svg_attributes']['height'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Height'),
      '#size' => 10,
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('svg_attributes')['height'],
    ];

    return $element;
  }

}
