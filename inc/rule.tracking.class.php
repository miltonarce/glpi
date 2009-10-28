<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TrackingBusinessRuleCollection extends RuleCollection {

   /**
    * Constructor
   **/
   function __construct() {
      parent::__construct(RULE_TRACKING_AUTO_ACTION);

      $this->rule_class_name="TrackingBusinessRule";
      $this->right="rule_ticket";
      $this->use_output_rule_process_as_next_input=true;
      $this->menu_option="ticket";
   }

   function getTitle() {
      global $LANG;

      return $LANG['rulesengine'][28];
   }

   function preProcessPreviewResults($output) {
      return showPreviewAssignAction($output);
   }

}


class TrackingBusinessRule extends Rule {

   /**
    * Constructor
   **/
   function __construct() {

      $this->table = "glpi_rules";
      $this->type = -1;
      $this->sub_type = RULE_TRACKING_AUTO_ACTION;
      $this->right="rule_ticket";
      $this->can_sort=true;
   }

   function maxActionsCount() {
      global $RULES_ACTIONS;

      return count($RULES_ACTIONS[RULE_TRACKING_AUTO_ACTION]);
   }

   function addSpecificParamsForPreview($input,$params) {

      if (!isset($params["entities_id"])) {
         $params["entities_id"] = $_SESSION["glpiactive_entity"];
      }
      return $params;
   }

   /**
    * Function used to display type specific criterias during rule's preview
    * @param $fields fields values
    */
   function showSpecificCriteriasForPreview($fields) {
      echo "<input type='hidden' name='entities_id' value='".$_SESSION["glpiactive_entity"]."'>";
   }

   function executeActions($output,$params,$regex_results) {

      if (count($this->actions)) {
         foreach ($this->actions as $action) {
            switch ($action->fields["action_type"]) {
               case "assign" :
                  $output[$action->fields["field"]] = $action->fields["value"];
                  break;

               case "affectbyip" :
               case "affectbyfqdn" :
               case "affectbymac" :
                  if (!isset($output["entities_id"])) {
                     $output["entities_id"]=$params["entities_id"];
                  }
                  $regexvalue = getRegexResultById($action->fields["value"],$regex_results);
                  switch ($action->fields["action_type"]) {
                     case "affectbyip" :
                        $result = getUniqueObjectIDByIPAddressOrMac($regexvalue,"IP",
                                                                    $output["entities_id"]);
                        break;

                     case "affectbyfqdn" :
                        $result= getUniqueObjectIDByFQDN($regexvalue,$output["entities_id"]);
                        break;

                     case "affectbymac" :
                        $result = getUniqueObjectIDByIPAddressOrMac($regexvalue,"MAC",
                                                                    $output["entities_id"]);
                        break;

                     default:
                        $result=array();
                  }
                  if (!empty($result)) {
                     $output["itemtype"]=$result["itemtype"];
                     $output["items_id"]=$result["id"];
                  }
                  break;
            }
         }
      }
      return $output;
   }

   function preProcessPreviewResults($output) {
      return showPreviewAssignAction($output);
   }

}

?>