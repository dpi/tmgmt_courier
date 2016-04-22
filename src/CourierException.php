<?php

namespace Drupal\tmgmt_courier;

/**
 * Courier Exception class.
 */
class CourierException extends \Exception {

  /**
   * CourierException constructor.
   *
   * @param string $message
   *   The informative message for the exception.
   * @param array $data
   *   Associative array of dynamic data that will be inserted into $message.
   * @param int $code
   *   Error code.
   */
  public function __construct($message = "", $data = array(), $code = 0) {
    parent::__construct(strtr($message, $data), $code);
  }

}
