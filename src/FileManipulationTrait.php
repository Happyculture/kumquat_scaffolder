<?php

namespace Drupal\Console\KumquatScaffolder;

use Symfony\Component\Filesystem\Filesystem;

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

}
