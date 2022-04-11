<?php


class CRM_Utils_Hooks_Payment2c2p {
//  public static function redsystoken($contact_id, $contribution_id, $token, $amount) {
//    $names = ['contact_id', 'contribution_id', 'token', 'amount'];
//    $args = [$contact_id, $contribution_id, $token, $amount];
//
//    return self::invokeHook($names, $args, 'civicrm_redsystoken');
//  }
//
//  private static function invokeHook($names, &$args, $fnSuffix) {
//    // Invoke classic hooks (legacy)
//    for ($i = 0; $i < 6; $i++) {
//      if (!isset($args[$i])) {
//        $args[$i] = CRM_Utils_Hook::$_nullObject;
//      }
//    }
//    CRM_Utils_Hook::singleton()
//      ->invoke($names, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $fnSuffix);
//
//    // Invoke Symfony Dispatch (new convention)
//    $event = \Civi\Core\Event\GenericHookEvent::createOrdered(
//      $names,
//      [&$args[0], &$args[1], &$args[2], &$args[3], &$args[4], &$args[5]]
//    );
//    $fnSuffix = str_replace("civicrm_", "", $fnSuffix);
//
//    \Civi::dispatcher()->dispatch('redsys.' . $fnSuffix, $event);
//    return $event->getReturnValues();
//  }

}