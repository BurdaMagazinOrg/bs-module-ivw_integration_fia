<?php

/**
 * @file
 * Contains Drupal\ivw_integration\Plugin\Field\FieldFormatter\IvwEmptyFormatter.
 */

namespace Drupal\ivw_integration_fia\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Drupal\ivw_integration\IvwLookupServiceInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ivw_fia_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "ivw_fia_formatter",
 *   module = "ivw_integration_fia",
 *   label = @Translation("FIA formatter"),
 *   field_types = {
 *     "ivw_integration_settings"
 *   }
 * )
 */
class IvwFiaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\ivw_integration\IvwLookupServiceInterface
   */
  protected $ivwLookupService;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * Constructs an IvwFiaFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\ivw_integration\IvwLookupServiceInterface $ivwLookupService
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Utility\Token $token
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    IvwLookupServiceInterface $ivwLookupService,
    ConfigFactoryInterface $configFactory,
    Token $token
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
    $this->ivwLookupService = $ivwLookupService;
    $this->configFactory = $configFactory;
    $this->tokenService = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('ivw_integration.lookup'),
      $container->get('config.factory'),
      $container->get('token')
    );
  }

  /**
   * Helper callback that alters returned token replacements.
   *
   * This is needed as we always want "delivery" ("D") set to "2" with FBIA,
   * but there is no way to configure this outside of the node / term config
   * hierarchy.
   *
   * @param array $replacements
   * @param array $data
   * @param array $options
   * @param \Drupal\Core\Render\BubbleableMetadata|NULL $bubbleable_metadata
   */
  public static function alterReplacements(
    array &$replacements,
    array $data = [],
    array $options = [],
    BubbleableMetadata $bubbleable_metadata = NULL
  ) {
    if (isset($replacements['[ivw:delivery]'])) {
      // Token calls the callback after escaping the replacements...
      $replacements['[ivw:delivery]'] = new HtmlEscapedText('2');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(
    FieldItemListInterface $items,
    $langcode = NULL
  ) {
    $elements = [];
    $fiaDomainPrefix = $this->configFactory->get('ivw_integration_fia.settings')
      ->get('fia_domain_prefix');

    // core token devs don't like the concept of callable
    // getting the method of the static class as closure keeps the callback overridable by subclasses
    $class = new ReflectionClass(static::class);
    $callback = $class->getMethod('alterReplacements')->getClosure();

    foreach ($items as $delta => $item) {
      $url = $fiaDomainPrefix . '?' . http_build_query(
          array(
            'st' => $this->configFactory->get('ivw_integration.settings')->get(
              'mobile_site'
            ),
            'cp' => $this->tokenService->replace(
              $this->configFactory->get('ivw_integration.settings')->get(
                'code_template'
              ),
              ['entity' => $items->getEntity()],
              [
                'sanitize' => FALSE,
                'callback' => $callback,
              ]
            ),
          )
        );

      $elements[$delta] = [
        '#theme' => 'ivw_fia',
        '#fia_frame_url' => $url,
      ];
    }
    return $elements;
  }


}
