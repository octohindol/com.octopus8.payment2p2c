<?php

require_once 'payment2c2p.civix.php';

// phpcs:disable
use CRM_Payment2c2p_ExtensionUtil as E;

// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function payment2c2p_civicrm_config(&$config)
{
    _payment2c2p_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function payment2c2p_civicrm_xmlMenu(&$files)
{
    _payment2c2p_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function payment2c2p_civicrm_install()
{
    _payment2c2p_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function payment2c2p_civicrm_postInstall()
{
    _payment2c2p_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function payment2c2p_civicrm_uninstall()
{
    _payment2c2p_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function payment2c2p_civicrm_enable()
{
    _payment2c2p_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function payment2c2p_civicrm_disable()
{
    _payment2c2p_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function payment2c2p_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL)
{
    return _payment2c2p_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function payment2c2p_civicrm_managed(&$entities)
{
    _payment2c2p_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Add CiviCase types provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function payment2c2p_civicrm_caseTypes(&$caseTypes)
{
    _payment2c2p_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Add Angular modules provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function payment2c2p_civicrm_angularModules(&$angularModules)
{
    // Auto-add module files from ./ang/*.ang.php
    _payment2c2p_civix_civicrm_angularModules($angularModules);
}


//CRM_Contribute_Form_ContributionView
/**
 * @param $formName
 * @param $form CRM_Core_Form
 */
function payment2c2p_civicrm_buildForm($formName, &$form)
{
    if ($formName == 'CRM_Contribute_Form_ContributionView') {
//                CRM_Core_Error::debug_var('form', $form);
        $isHasAccess = FALSE;
        $action = 'update';
        $id = $form->get('id');
        try {
            $isHasAccess = Civi\Api4\Contribution::checkAccess()
                ->setAction($action)
                ->addValue('id', $id)
                ->execute()->first()['access'];
        } catch (API_Exception $e) {
            $isHasAccess = FALSE;
        }
        if ($isHasAccess) {

            $contribution = Civi\Api4\Contribution::get(TRUE)
                ->addWhere('id', '=', $id)->addSelect('*')->execute()->first();
            if (empty($contribution)) {
                CRM_Core_Error::statusBounce(ts('Access to contribution not permitted'));
            }
            // We just cast here because it was traditionally an array called values - would be better
            // just to use 'contribution'.
            $values = (array)$contribution;
            $invoiceId = $values['invoice_id'];
            $contributionStatus = CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $values['contribution_status_id']);
            if ($contributionStatus == 'Pending') {
                CRM_Core_Error::debug_var('values', $values);
                CRM_Core_Error::debug_var('contributionStatus', $contributionStatus);
                if (isset($form->get_template_vars()['linkButtons'])) {
                    $linkButtons = $form->get_template_vars()['linkButtons'];
                    $urlParams = "reset=1&invoiceId={$invoiceId}";
                    $linkButtons[] = [
                        'title' => ts('Update Status from 2c2p'),
//                'name' => ts('Update Status from 2c2p'),
                        'url' => 'civicrm/payment2c2p/checkpending',
                        'qs' => $urlParams,
                        'icon' => 'fa-pencil',
                        'accessKey' => 'u',
                        'ref' => '',
                        'name' => '',
                        'extra' => '',
                    ];
                    $form->assign('linkButtons', $linkButtons ?? []);
                }
            }
        }
    }
}


/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function payment2c2p_civicrm_alterSettingsFolders(&$metaDataFolders = NULL)
{
    _payment2c2p_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function payment2c2p_civicrm_entityTypes(&$entityTypes)
{
    _payment2c2p_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function payment2c2p_civicrm_themes(&$themes)
{
    _payment2c2p_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function payment2c2p_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu()
 *
 * @param array $menu
 * @return void
 */
function payment2c2p_civicrm_navigationMenu(&$menu)
{
    _payment2c2p_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', [
        'label' => E::ts('2c2p Settings'),
        'name' => '2c2p_settings',
        'url' => 'civicrm/payment2c2p/settings',
        'permission' => 'administer CiviCRM',
        'operator' => 'OR',
        'has_separator' => 1,
        'is_active' => 1,
    ]);
    _payment2c2p_civix_navigationMenu($menu);
}
