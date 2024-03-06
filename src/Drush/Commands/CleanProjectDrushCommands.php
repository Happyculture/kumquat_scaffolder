<?php

namespace KumquatScaffolder\Drush\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Helper\Renderer\TwigRenderer;
use DrupalCodeGenerator\Twig\TwigEnvironment;
use DrupalCodeGenerator\Validator\Chained;
use DrupalCodeGenerator\Validator\Required;
use Drush\Attributes as CLI;
use Psr\Container\ContainerInterface as DrushContainer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Twig\Loader\FilesystemLoader as TemplateLoader;

/**
 * Kumquat Scaffolder clean project command.
 */
class CleanProjectDrushCommands extends DrushCommandsGeneratorBase {

  const THEMES_FOLDER = 'themes/custom';
  const MODULES_FOLDER = 'modules/custom';
  const PROFILES_FOLDER = 'profiles';

  const REGEX_MACHINE_NAME = '/^[a-z0-9_]+$/';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    Filesystem $fileSystem,
    TwigRenderer $renderer,
    protected readonly Finder $finder,
  ) {
    parent::__construct($fileSystem, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public static function createEarly(DrushContainer $drush_container): static {
    return new static(
      new Filesystem(),
      new TwigRenderer(new TwigEnvironment(new TemplateLoader())),
      new Finder(),
    );
  }

  /**
   * Generate project command.
   */
  #[CLI\Command(name: 'kumquat:clean-project', aliases: ['kcp'])]
  #[CLI\Option(
    name: 'machine-name',
    description: 'The project (short) machine name.',
    suggestedValues: ['hc', 'my-client'])]
  #[CLI\Option(
    name: 'config-folder',
    description: 'The configuration storage folder, relative to the document root.',
    suggestedValues: ['../config/sync'])]
  #[CLI\Option(
    name: 'clean-all',
    description: 'Clean the install profile, the core module, the admin theme, the front theme and the associated configuration. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-profile',
    description: 'Clean the install profile. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-core-module',
    description: 'Clean the core module. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-content-modules',
    description: 'Clean the content modules. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-theme',
    description: 'Clean the front theme. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-admin-theme',
    description: 'Clean the administration theme. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'clean-config',
    description: 'Change the config to use the default profile and themes by default. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Usage(name: 'drush kumquat:clean-project', description: 'Run with wizard')]
  public function generateEnvironment(array $options = [
    'machine-name' => self::REQ,
    'config-folder' => self::REQ,
    'clean-all' => self::OPT,
    'clean-profile' => self::OPT,
    'clean-core-module' => self::OPT,
    'clean-content-modules' => self::OPT,
    'clean-theme' => self::OPT,
    'clean-admin-theme' => self::OPT,
    'clean-config' => self::OPT,
    'dry-run' => FALSE,
  ]): int {
    return $this->generate($options);
  }

  /**
   * {@inheritdoc}
   */
  protected function extractOptions(array $options): array {
    $vars = [
      'machine_name' => $options['machine-name'],
      'config_folder' => $options['config-folder'],
    ];
    return array_filter($vars, fn ($value) => !\is_null($value));
  }

  /**
   * {@inheritdoc}
   */
  protected function interview(array &$vars): void {
    $cleaner_parts = [
      'all',
      'profile',
      'core-module',
      'content-modules',
      'theme',
      'admin-theme',
      'config',
    ];

    $enabled_parts = [];
    foreach ($cleaner_parts as $part) {
      $enabled_parts[$part] = !empty($this->input()->getOption('clean-' . $part));
    }
    $enabled_parts = array_filter($enabled_parts);

    if (empty($enabled_parts)) {
      /** @var array $enabled_parts */
      $choices = $this->io()->choice(
        'What do you want to remove? Use comma separated values for multiple selection.',
        $cleaner_parts,
        0,
        TRUE
      );

      foreach ($cleaner_parts as $index => $part) {
        $enabled_parts[$part] = in_array($index, $choices);
        $this->input()->setOption('clean-' . $part, $enabled_parts[$part]);
      }
    }

    if (!isset($vars['machine_name'])) {
      $composerData = Json::decode(file_get_contents($this->drupalFinder()->getComposerRoot() . '/composer.json'));
      [, $default_name] = explode('/', $composerData['name']);
      $vars['machine_name'] = $this->io()->ask(
        'What is the machine name of the project? (modules and theme machine names are derived from it)',
        $default_name,
        new Chained(
          new Required(),
          static fn (string $value): string => static::validateMachineName($value),
        ),
      );
    }

    if ($enabled_parts['config'] || $enabled_parts['admin-theme'] || $enabled_parts['theme'] || $enabled_parts['all']) {
      if (!isset($vars['config_folder'])) {
        $app_root = $this->drupalFinder()->getDrupalRoot();
        $vars['config_folder'] = $this->io()->ask(
          'Where are the configuration files stored (relative to the document root)?',
          '../config/sync',
          new Chained(
            new Required(),
            static fn (string $value): string => static::validatePath($value, $app_root),
          ),
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function validateVars(array $vars): void {
    if (isset($vars['machine_name'])) {
      static::validateMachineName($vars['machine_name']);
    }

    if (isset($vars['config_folder'])) {
      $app_root = $this->drupalFinder()->getDrupalRoot();
      static::validatePath($vars['config_folder'], $app_root);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getVarsSummary(array $vars): array {
    $clean_all = (bool) $this->input()->getOption('clean-all');
    $clean_profile = (bool) $this->input()->getOption('clean-profile');
    $clean_core_module = (bool) $this->input()->getOption('clean-core-module');
    $clean_content_modules = (bool) $this->input()->getOption('clean-content-modules');
    $clean_theme = (bool) $this->input()->getOption('clean-theme');
    $clean_admin_theme = (bool) $this->input()->getOption('clean-admin-theme');
    $clean_config = (bool) $this->input()->getOption('clean-config');

    // Improve attributes readibility.
    $recap_gen_profile = $clean_profile || $clean_all ? 'Yes' : 'No';
    $recap_gen_core_module = $clean_core_module || $clean_all ? 'Yes' : 'No';
    $recap_gen_content_modules = $clean_content_modules || $clean_all ? 'Yes' : 'No';
    $recap_gen_theme = $clean_theme || $clean_all ? 'Yes' : 'No';
    $recap_gen_admin_theme = $clean_admin_theme || $clean_all ? 'Yes' : 'No';
    $recap_gen_config = $clean_config || $clean_all ? 'Yes' : 'No';

    $summary = [
      'Machine name' => $vars['machine_name'],
    ];
    $summary['Clean profile'] = $recap_gen_profile;
    $summary['Clean core module'] = $recap_gen_core_module;
    $summary['Clean content modules'] = $recap_gen_content_modules;
    $summary['Clean front theme'] = $recap_gen_theme;
    $summary['Clean admin theme'] = $recap_gen_admin_theme;
    $summary['Clean config'] = $recap_gen_config;
    if ($clean_config || $clean_all) {
      $summary['Config folder'] = $vars['config_folder'];
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function postGenerate(array $vars): void {
    $clean_all = (bool) $this->input()->getOption('clean-all');
    $clean_profile = (bool) $this->input()->getOption('clean-profile');
    $clean_core_module = (bool) $this->input()->getOption('clean-core-module');
    $clean_content_modules = (bool) $this->input()->getOption('clean-content-modules');
    $clean_theme = (bool) $this->input()->getOption('clean-theme');
    $clean_admin_theme = (bool) $this->input()->getOption('clean-admin-theme');
    $clean_config = (bool) $this->input()->getOption('clean-config');

    if ($clean_profile || $clean_all) {
      $this->cleanProfile($vars);
    }

    if ($clean_core_module || $clean_all) {
      $this->cleanCoreModule($vars);
    }

    if ($clean_content_modules || $clean_all) {
      $this->cleanContentModules($vars);
    }

    if ($clean_theme || $clean_all) {
      $this->cleanTheme($vars);
    }

    if ($clean_admin_theme || $clean_all) {
      $this->cleanAdminTheme($vars);
    }

    if ($clean_config || $clean_all) {
      $this->cleanConfig($vars);
    }
  }

  /**
   * Clean install profile.
   */
  protected function cleanProfile(array $vars): void {
    $machine_name = $vars['machine_name'];
    $dir = static::PROFILES_FOLDER . '/' . $machine_name;
    if ($this->fileSystem->exists($dir)) {
      // Remove profile directory.
      $this->fileSystem->remove($dir);

      $this->io()->success(sprintf(
        '%s profile successfully cleaned.',
        $machine_name
      ));
    }
    else {
      $this->io()->info(sprintf(
        'No %s profile to clean.',
        $machine_name
      ));
    }
  }

  /**
   * Clean core module.
   */
  protected function cleanCoreModule(array $vars): void {
    $machine_name = $vars['machine_name'] . '_core';
    $dir = static::MODULES_FOLDER . '/' . $machine_name;
    if ($this->fileSystem->exists($dir)) {
      $this->fileSystem->remove($dir);
      $this->io()->success(sprintf(
        '%s core module successfully cleaned.',
        $machine_name
      ));
    }
    else {
      $this->io()->info(sprintf(
        'No %s core module to clean.',
        $machine_name
      ));
    }
  }

  /**
   * Clean content modules.
   */
  protected function cleanContentModules(array $vars): void {
    foreach (['deploy', 'test'] as $mode) {
      $machine_name = $vars['machine_name'] . '_content_' . $mode;
      $dir = static::MODULES_FOLDER . '/' . $machine_name;
      if ($this->fileSystem->exists($dir)) {
        $this->fileSystem->remove($dir);
        $this->io()->success(sprintf(
          '%s content module successfully cleaned.',
          $machine_name
        ));
      }
      else {
        $this->io()->info(sprintf(
          'No %s content module to clean.',
          $machine_name
        ));
      }
    }
  }

  /**
   * Clean front theme.
   */
  protected function cleanTheme(array $vars): void {
    $machine_name = $vars['machine_name'] . '_theme';
    $dir = static::THEMES_FOLDER . '/' . $machine_name;
    if ($this->fileSystem->exists($dir)) {
      $this->fileSystem->remove($dir);

      // Remove theme configuration.
      $this->fileSystem->remove($vars['config_folder'] . '/' . $machine_name . '.settings.yml');

      // Remove block configuration in the config dir.
      $files = $this->finder->in($vars['config_folder'])
        ->files()->name('block.block.' . $machine_name . '__*.yml');
      $this->fileSystem->remove($files);

      // Remove the theme path in the composer.json file.
      $prevDir = getcwd();
      chdir($this->drupalFinder->getComposerRoot());
      $enabledThemes = json_decode(exec('/usr/bin/env composer config extra.kumquat-themes'));

      $prefix = substr($this->drupalFinder->getDrupalRoot(), strlen($this->drupalFinder->getComposerRoot()));
      $prefix = trim($prefix, '/');
      $key = array_search($prefix . '/' . $dir, $enabledThemes);
      if ($key !== FALSE) {
        unset($enabledThemes[$key]);
      }

      exec('/usr/bin/env composer config extra.kumquat-themes --json \'' . json_encode(array_unique($enabledThemes)) . '\'');
      exec('/usr/bin/env composer update --lock');

      // Reset the theme path in the .lando.yml file.
      if (count($enabledThemes) === 0 && $this->fileSystem->exists('.lando.yml')) {
        $yaml = Yaml::decode(file_get_contents('.lando.yml'));
        foreach ($yaml['tooling'] as &$settings) {
          if (isset($settings['dir']) && $settings['dir'] === '/app/web/themes/custom/' . $machine_name) {
            $settings['dir'] = '/app/web/themes/custom/kumquat_theme';
          }
        }
        file_put_contents('.lando.yml', Yaml::encode($yaml));
      }

      chdir($prevDir);

      $this->io()->success(sprintf('%s front theme successfully cleaned.', $machine_name));
    }
    else {
      $this->io()->info(sprintf('No %s front theme to clean.', $machine_name));
    }
  }

  /**
   * Clean administration theme.
   */
  protected function cleanAdminTheme(array $vars): void {
    $machine_name = $vars['machine_name'] . '_admin_theme';
    $dir = static::THEMES_FOLDER . '/' . $machine_name;
    if ($this->fileSystem->exists($dir)) {
      // Remove theme directory.
      $this->fileSystem->remove($dir);

      // Remove theme configuration.
      $this->fileSystem->remove($vars['config_folder'] . '/' . $machine_name . '.settings.yml');

      // Remove block configuration in the config dir.
      $files = $this->finder->in($vars['config_folder'])
        ->files()->name('block.block.' . $machine_name . '__*.yml');
      $this->fileSystem->remove($files);

      $this->io()->success(sprintf('%s admin theme successfully cleaned.', $machine_name));
    }
    else {
      $this->io()->info(sprintf('No %s admin theme to clean.', $machine_name));
    }
  }

  /**
   * Clean configuration.
   */
  protected function cleanConfig(array $vars): void {
    // Set themes in the system.theme.yml file.
    $filename = $vars['config_folder'] . '/system.theme.yml';
    $config = Yaml::decode(file_get_contents($filename));

    if ($config['admin'] === $vars['machine_name'] . '_admin_theme') {
      $config['admin'] = 'seven';
      $resetAdminTheme = TRUE;
    }
    if ($config['default'] === $vars['machine_name'] . '_theme') {
      $config['default'] = 'olivero';
      $resetFrontTheme = TRUE;
    }

    $this->fileSystem->dumpFile($filename, Yaml::encode($config));

    // Update profile and themes in the core.extension.yml file.
    $filename = $vars['config_folder'] . '/core.extension.yml';
    $config = Yaml::decode(file_get_contents($filename));
    $current_profile = $config['profile'];

    if ($current_profile === $vars['machine_name']) {
      $config['module']['minimal'] = 1000;
      $config['profile'] = 'minimal';
    }
    unset($config['module'][$vars['machine_name']]);
    unset($config['module'][$vars['machine_name'] . '_core']);
    $config['module'] = module_config_sort($config['module']);

    if (isset($resetFrontTheme)) {
      $config['theme']['olivero'] = 0;
    }
    unset($config['theme'][$vars['machine_name'] . '_theme']);
    if (isset($resetAdminTheme)) {
      $config['theme']['seven'] = 0;
    }
    unset($config['theme'][$vars['machine_name'] . '_admin_theme']);

    $this->fileSystem->dumpFile($filename, Yaml::encode($config));

    // Reset the profile name from the combawa's install script if it's used on
    // the project.
    $prevDir = getcwd();
    chdir($this->drupalFinder->getComposerRoot());

    $install_path = 'scripts/combawa/install.sh';
    if (file_exists($install_path)) {
      $install_script = file_get_contents($install_path);
      preg_match('/^\$DRUSH site-install.* (.*?)$/mi', $install_script, $matches);
      if (trim($matches[1], '\'" ') === $vars['machine_name']) {
        $install_script = preg_replace('/^\$DRUSH site-install(.*?) ' . preg_quote($vars['machine_name']) . '\s*$/mi', '$DRUSH site-install $1 minimal' . "\n", $install_script);
        file_put_contents($install_path, $install_script);
      }
    }

    chdir($prevDir);

    $this->io()->success('Configuration successfully cleaned.');
  }

  /**
   * {@inheritdoc}
   */
  protected function collectAssets(AssetCollection $assets, array $vars): void {}

}
