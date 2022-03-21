<?php

namespace Drupal\Console\KumquatScaffolder;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Helper trait to manipulate files.
 */
trait FileManipulationTrait {

  /**
   * The file system service.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * The file finder service.
   *
   * @var \Symfony\Component\Finder\Finder
   */
  protected $finder;

  /**
   * Gets an helper to manipulate files.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   The file system service.
   */
  public function getFs() {
    if (NULL === $this->fs) {
      $this->fs = new Filesystem();
    }
    return $this->fs;
  }

  /**
   * Gets an helper to find files.
   *
   * @return \Symfony\Component\Finder\Finder
   *   The file finder service.
   */
  public function getFinder() {
    if (NULL === $this->finder) {
      $this->finder = new Finder();
    }
    return $this->finder;
  }

}
