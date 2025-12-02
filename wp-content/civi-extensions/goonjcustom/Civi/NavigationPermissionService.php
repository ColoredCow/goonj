<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Aws\S3\S3Client;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';




/**
 *
 */
class NavigationPermissionService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_navigationMenu' => ['hideNavForRoles'],
      '&hook_civicrm_pageRun' => [
        ['hideButtonsForMMT'],
        ['hideAPIKeyTab'],
        ['hideContributionFields'],
        ['testing']
      ],
      '&hook_civicrm_post' => [
        ['uploadFileTos3'],
      ],
    ];
  }


  public static function uploadFileTos3(string $op, string $objectName, int $objectId, &$objectRef) {
    error_log("[S3-UPLOAD] Hook triggered. Op=$op, ObjName=$objectName, ObjId=$objectId");

    // Only handle new File creation
    if ($objectName !== 'File' || $op !== 'create') {
        error_log("[S3-UPLOAD] Skipping: not a File create operation.");
        return;
    }

    // Step 1: Fetch file info
    try {
        $file = civicrm_api3('File', 'getsingle', ['id' => $objectId]);
        error_log("[S3-UPLOAD] File info fetched: " . print_r($file, true));
    } catch (\Exception $e) {
        error_log("[S3-UPLOAD][ERROR] Unable to fetch file info: " . $e->getMessage());
        return;
    }

    // Safely get URI
    $fileUri = $file['uri'] ?? '';
    if (empty($fileUri)) {
        error_log("[S3-UPLOAD][ERROR] File URI is empty for File ID: $objectId");
        return;
    }

    // Step 2: Build absolute path
    $uploadDir = \Civi::paths()->getPath('[civicrm.files]/custom/'); // custom folder
    $absolutePath = $uploadDir . $fileUri;
    error_log("[S3-UPLOAD] Absolute local path: $absolutePath");

    if (!file_exists($absolutePath)) {
        error_log("[S3-UPLOAD][ERROR] File does not exist at path: $absolutePath");
        return;
    }

    // Step 3: Upload to S3
    $s3Url = self::upload_to_s3($absolutePath, $file['mime_type'] ?? 'application/octet-stream');
    if (!$s3Url) {
        error_log("[S3-UPLOAD][ERROR] S3 upload failed.");
        return;
    }

    error_log("[S3-UPLOAD] Successfully uploaded to S3: $s3Url");

    // Step 4: Update CiviCRM file record
    try {
        civicrm_api3('File', 'create', [
            'id' => $objectId,
            'uri' => $s3Url,
        ]);
        error_log("[S3-UPLOAD] Updated CiviCRM file record to use S3 URI.");
    } catch (\Exception $e) {
        error_log("[S3-UPLOAD][ERROR] Unable to update CiviCRM file URI: " . $e->getMessage());
    }
}

public static function upload_to_s3(string $localPath, string $mime): ?string {
    // Ensure AWS SDK is loaded
    if (!class_exists(S3Client::class)) {
        error_log("[S3-UPLOAD][ERROR] AWS SDK not loaded. Please run: composer require aws/aws-sdk-php");
        return null;
    }

    // Check credentials
    if (!defined('AWS_KEY') || !defined('AWS_SECRET') || !defined('S3_BUCKET') || empty(AWS_KEY) || empty(AWS_SECRET) || empty(S3_BUCKET)) {
        error_log("[S3-UPLOAD][ERROR] AWS credentials or bucket not defined.");
        return null;
    }

    try {
        $s3 = new S3Client([
            'region'  => 'ap-south-1',
            'version' => 'latest',
            'credentials' => [
                'key'    => AWS_KEY,
                'secret' => AWS_SECRET,
            ],
        ]);

        $key = 'civicrm/uploads/' . basename($localPath);
        error_log("[S3-UPLOAD] Uploading file to S3 with key: $key");

        $result = $s3->putObject([
            'Bucket' => S3_BUCKET,
            'Key'    => $key,
            'SourceFile' => $localPath,
            'ContentType' => $mime,
            // 'ACL' => 'public-read',
        ]);

        return $result['ObjectURL'] ?? null;

    } catch (\Exception $e) {
        error_log("[S3-UPLOAD][ERROR] Exception in upload_to_s3: " . $e->getMessage());
        return null;
    }
}




/**
 * Override file and contact-photo output to use S3.
 */
public function testing(&$page) {
    error_log('Page class: ' . get_class($page));

    /*
     * ----------------------------------------------------
     * 1️⃣ SEARCHKIT + FILE FIELDS (File Handler Override)
     * ----------------------------------------------------
     */
    if (get_class($page) === 'CRM_Core_Page_File') {
        error_log("File handler override triggered");

        $fileId = \CRM_Utils_Request::retrieve('id', 'Integer');
        error_log("Retrieved fileId from URL: " . print_r($fileId, true));
        if (!$fileId) return;

        try {
            $file = civicrm_api3('File', 'getsingle', ['id' => $fileId]);
        } catch (\Exception $e) {
            error_log("Error fetching civicrm_file: " . $e->getMessage());
            return;
        }

        $filename = $file['uri'];
        $mime = $file['mime_type'];
        $s3Url = "https://goonj-uploads-items.s3.ap-south-1.amazonaws.com/custom/" . $filename;
        error_log("S3 URL built: " . $s3Url);

        $image = @file_get_contents($s3Url);
        if ($image === FALSE) {
            error_log("S3 file not found at URL: " . $s3Url);
            return;
        }

        header("Content-Type: {$mime}");
        header("Content-Length: " . strlen($image));
        echo $image;
        \CRM_Utils_System::civiExit();
    }

    if (get_class($page) === 'CRM_Contact_Page_View_Summary') {
      error_log("Contact Summary page detected");
  
      $contactId = $page->getVar('_contactId');
      error_log("Contact ID: " . print_r($contactId, true));
      if (!$contactId) return;
  
      // Get custom QR code file ID
      try {
          $contacts = \Civi\Api4\Contact::get(TRUE)
              ->addSelect('Contact_QR_Code.QR_Code')
              ->addWhere('id', '=', $contactId)
              ->execute()
              ->first();
      } catch (\Exception $e) {
          error_log("Error fetching contact QR code: " . $e->getMessage());
          return;
      }
  
      $fileId = $contacts['Contact_QR_Code.QR_Code'];
      if (!$fileId) return;
  
      // Load civicrm_file record
      try {
          $file = civicrm_api3('File', 'getsingle', ['id' => $fileId]);
      } catch (\Exception $e) {
          error_log("Error fetching civicrm_file: " . $e->getMessage());
          return;
      }
  
      $s3Url = "https://goonj-uploads-items.s3.ap-south-1.amazonaws.com/custom/" . $file['uri'];
      error_log("S3 URL for contact QR code: " . $s3Url);
  
      // Assign S3 URL to custom field token and fileUrl
      $customFields = \Civi\Api4\CustomField::get(TRUE)
          ->addSelect('id')
          ->addWhere('custom_group_id:name', '=', 'Contact_QR_Code')
          ->addWhere('name', '=', 'QR_Code')
          ->setLimit(1)
          ->execute()
          ->first();
  
      $tokenName = 'custom_' . $customFields['id'];
      $page->assign($tokenName, $s3Url);
      $page->assign("fileUrl_{$fileId}", $s3Url);
      error_log("Custom field image assigned successfully to token and fileUrl");
  
      // ----------------------------
      // Inject JS safely via CRM_Core_Resources
      // ----------------------------
      $js = <<<JS
      document.addEventListener('DOMContentLoaded', function() {
          var qrDiv = document.querySelector('#custom-set-content-3 .crm-content.crm-custom-data');
          if(qrDiv && !qrDiv.querySelector('img')) {
              qrDiv.innerHTML = '<img src="{$s3Url}" alt="QR Code" style="max-width:150px;height:auto;">';
              console.log('QR code injected for contact {$contactId}');
          }
      });
  JS;
  
      \CRM_Core_Resources::singleton()->addScript($js, 0, 'inline');
      error_log("JS injected via CRM_Core_Resources for inline rendering");
  }

  
  
}



  /**
   *
   */
  public function hideContributionFields(&$page) {
    if ($page->getVar('_name') === 'CRM_Eck_Page_Entity_View') {
      \CRM_Core_Resources::singleton()->addScript("
    (function($) {
      $(document).ready(function() {
      const searchParams = new URLSearchParams(window.location.search);
      const hasGoonjActivities = searchParams.has('goonj_activites');

      if (hasGoonjActivities) {
        const labelsToHide = [
        'Total Number of unique contributors',
        'Total Number of unique material contributors'
        ];

          $('table.crm-info-panel tr').each(function() {
            const label = $(this).find('td.label').text().trim();
            if (labelsToHide.includes(label)) {
              $(this).hide();
            }
          });
        }
      });
    })(CRM.$);
  ");
    }
  }

  /**
   *
   */
  public function hideAPIKeyTab(&$page) {
    if ($page->getVar('_name') === 'CRM_Contact_Page_View_Summary') {
      if (!\CRM_Core_Permission::check('admin')) {
        \CRM_Core_Resources::singleton()->addScript("
          document.addEventListener('DOMContentLoaded', function() {
            const apiTab = document.querySelector('#tab_apiKey');
            if (apiTab) {
              apiTab.style.display = 'none';
            }
          });
        ");
      }
    }
  }

  /**
   *
   */
  public function hideButtonsForMMT(&$page) {
    if ($page->getVar('_name') === 'CRM_Contact_Page_View_Summary') {
      if (\CRM_Core_Permission::check('mmt') && !\CRM_Core_Permission::check('admin')) {
        \CRM_Core_Resources::singleton()->addScript("
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.crm-actions-ribbon').forEach(el => el.style.display = 'none');
                    
                    document.querySelectorAll('afsearch-induction-details-of-contact').forEach(el => el.style.display = 'none');
                    
                    document.querySelectorAll('.crm-collapsible').forEach(function(el) {
                        const title = el.querySelector('.collapsible-title');
                        if (title && title.textContent.trim() === 'Volunteer Details') {
                            el.style.display = 'none';  // Hides the entire collapsible section
                        }
                    });
                });
            ");
      }
    }
  }

  /**
   *
   */
  public function hideNavForRoles(&$params) {
    $isAdmin = \CRM_Core_Permission::check('admin');
    if ($isAdmin) {
      return;
    }

    $roleMenuMapping = [
      'account_team' => [
        'hide_menus' => [
          'Offices',
          'Dropping Center',
          'Institution Collection Camp',
          'Institute',
          'Institutes',
          'Collection Camps',
          'Goonj Activities',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'My Office',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Dashboard',
          'Contribution Reports',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
          ],
      ],
      'mmt' => [
        'hide_menus' => [
          'Dropping Center',
          'Inductions',
          'Institution Collection Camp',
          'Collection Camps',
          'Goonj Activities',
          'Institutes',
          'Inductions',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Offices',
        ],
      ],
      'goonj_chapter_admin' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'urban_ops_admin' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Account - Individuals',
          'Account - Institutions',
          'eck_entities',
          'My Office',
          'Contributions',
        ],
      ],
      'urbanops' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Contributions',
          'Mailings',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'Manage Duplicates',
        ],
      ],
      'ho_account' => [
        'hide_menus' => [
          'Urban Visit',
          'Account: Goonj Offices',
          'Volunteers',
          'Institute',
          'Institutes',
          'Inductions',
          'Individuals',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'My Office',
        ],
      ],
      'communications_team' => [
        'hide_menus' => [
          'Volunteers',
          'Events',
          'Offices',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Inductions',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Institution Collection Camps',
          'Dropping Center',
          'Institution Goonj Activities',
        ],
      ],
      'sanjha_team' => [
        'hide_menus' => [
          'Induction',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Offices',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
        'hide_child_menus' => [
          'Institution Collection Camps',
        ],
      ],
      'data_team' => [
        'hide_menus' => [
          'Institute',
          'Volunteers',
          'Events',
          'Offices',
          'Inductions',
          'Volunteers',
          'Events',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'project_team_ho' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'project_team_chapter' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'njpc_ho_team' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
        'hide_child_menus' => [
          'Institution Collection Camps',
          'Material Contributions',
          'Dropping Center',
        ],
      ],
      's2s_ho_team' => [
        'hide_menus' => [
          'Inductions',
          'Individuals',
          'Volunteers',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'data_entry' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Contributions',
          'Contacts',
          'Events',
          'Campaigns',
          'Volunteers',
          'Urban Visit',
          'Induction Tab',
          'Induction',
          'Inductions',
        ],
      ],
      'mmt_and_accounts_chapter_team' => [
        'hide_menus' => [
          'Campaigns',
          'Offices',
          'Volunteers',
          'Individuals',
          'Induction Tab',
          'Induction',
          'Inductions',
          'Institutes',
          'My Office',
        ],
        'hide_child_menus' => [
          'Dashboard',
          'Contribution Reports',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
        ],
      ],
      'urban_ops_and_accounts_chapter_team' => [
        'hide_menus' => [
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'My Office',
          'Account - Individuals',
          'Account - Institutions',
        ],
        'hide_child_menus' => [
          'Contribution Reports',
          'Dashboard',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
        ],
      ],
      'project_ho_and_accounts' => [
        'hide_menus' => [
          'Induction Tab',
          'Induction',
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Account - Individuals',
          'Account - Institutions',
        ],
        'hide_child_menus' => [
          'New Contribution',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
        ],
      ]
    ];

    foreach ($roleMenuMapping as $role => $menuConfig) {
      if (\CRM_Core_Permission::check($role)) {
        $menusToHide = $menuConfig['hide_menus'] ?? [];
        $childMenusToHide = $menuConfig['hide_child_menus'] ?? [];

        foreach ($params as $key => &$menu) {
          // Hide top-level menu.
          if (isset($menu['attributes']['name']) && in_array($menu['attributes']['name'], $menusToHide)) {
            $menu['attributes']['active'] = 0;
          }

          // Hide child menus.
          if (isset($menu['child']) && is_array($menu['child'])) {
            foreach ($menu['child'] as $childKey => &$child) {
              if (isset($child['attributes']['name']) && in_array($child['attributes']['name'], $childMenusToHide)) {
                $child['attributes']['active'] = 0;
              }
            }
          }
        }
      }
    }
  }

}
