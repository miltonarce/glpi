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
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Reservation class
class Reservation extends CommonDBTM {

   // From CommonDBTM
   public $table = 'glpi_reservations';

   function pre_deleteItem($ID) {
      global $CFG_GLPI;

      if (isset($this->fields["users_id"])
          && ($this->fields["users_id"]==$_SESSION["glpiID"]
              || haveRight("reservation_central","w"))) {

         // Processing Email
         if ($CFG_GLPI["use_mailing"]) {
            $mail = new MailingResa($this,"delete");
            $mail->send();
         }
      }
      return true;
   }

   function prepareInputForUpdate($input) {


      $item=0;
      if (isset($input['_item'])) {
         $item=$_POST['_item'];
      }

      $this->getFromDB($input["id"]);
      // Save fields
      $oldfields=$this->fields;
      // Needed for test already planned
      $this->fields["begin"] = $input["begin"];
      $this->fields["end"] = $input["end"];

      if (!$this->test_valid_date()) {
         $this->displayError("date",$item);
         return false;
      }

      if ($this->is_reserved()) {
         $this->displayError("is_res",$item);
         return false;
      }

      // Restore fields
      $this->fields=$oldfields;

      return $input;
   }

   function post_updateItem($input,$updates,$history=1) {
      global $CFG_GLPI;

      if (count($updates) && $CFG_GLPI["use_mailing"]) {
         $mail = new MailingResa($this,"update");
         $mail->send();
      }
   }

   function prepareInputForAdd($input) {

      // Error on previous added reservation on several add
      if (isset($input['_ok']) && !$input['_ok']) {
         return false;
      }
 
      // set new date.
      $this->fields["reservationitems_id"] = $input["reservationitems_id"];
      $this->fields["begin"] = $input["begin"];
      $this->fields["end"] = $input["end"];

      if (!$this->test_valid_date()) {
         $this->displayError("date",$input["reservationitems_id"]);
         return false;
      }

      if ($this->is_reserved()) {
         $this->displayError("is_res",$input["reservationitems_id"]);
         return false;
      }

      return $input;
   }

   function post_addItem($newID,$input) {
      global $CFG_GLPI;

      if ($CFG_GLPI["use_mailing"]) {
         $mail = new MailingResa($this,"new");
         $mail->send();
      }
   }

   // SPECIFIC FUNCTIONS

   /**
    * Is the item already reserved ?
    *
    *@return boolean
    **/
   function is_reserved() {
      global $DB;

      if (!isset($this->fields["reservationitems_id"])
          || empty($this->fields["reservationitems_id"])) {
         return true;
      }

      // When modify a reservation do not itself take into account
      $ID_where="";
      if (isset($this->fields["id"])) {
         $ID_where=" `id` <> '".$this->fields["id"]."' AND ";
      }
      $query = "SELECT *
                FROM `".$this->table."`
                WHERE $ID_where
                         `reservationitems_id` = '".$this->fields["reservationitems_id"]."'
                     AND '".$this->fields["begin"]."' < `end`
                     AND '".$this->fields["end"]."' > `begin`";
      if ($result=$DB->query($query)) {
         return ($DB->numrows($result)>0);
      }
      return true;
   }

   /**
    * Current dates are valid ? begin before end
    *
    *@return boolean
    **/
   function test_valid_date() {
      return (!empty($this->fields["begin"]) && !empty($this->fields["end"])
              && strtotime($this->fields["begin"])<strtotime($this->fields["end"]));
   }

   /**
    * display error message
    * @param $type error type : date / is_res / other
    * @param $ID ID of the item
    * @param $target where to go on error
    *@return nothing
    **/
   function displayError($type,$ID) {
      global $LANG;

      echo "<br><div class='center'>";
      switch ($type) {
         case "date" :
            echo $LANG['planning'][1];
            break;

         case "is_res" :
            echo $LANG['reservation'][18];
            break;

         default :
            echo "Unknown error";
      }
      echo "<br><a href='reservation.php?reservationitems_id=$ID'>".$LANG['reservation'][20]."</a>";
      echo "</div>";
   }

   /**
    * Get text describing reservation
    *
   * @param $format text or html
    */
   function textDescription($format="text") {
      global $LANG;

      $ri=new ReservationItem();
      $name="";
      $tech="";
      if ($ri->getFromDB($this->fields["reservationitems_id"])) {
         if (class_exists($ri->fields['itemtype'])) {
            $item=$ri->fields['itemtype']();
            if ($item->getFromDB($ri->fields['items_id'])) {
               $name=$item->getTypeName()." ".$item->getName();
               if ($tmp=$item->getField('users_id_tech')) {
                  $tech=getUserName($tmp);
               }
            }
         }
      }

      $u=new User();
      $u->getFromDB($this->fields["users_id"]);
      $content="";
      if ($format=="html") {
         $content= "<html><head> <style type='text/css'>";
         $content.=".description{ color: inherit; background: #ebebeb; border-style: solid;
                                 border-color: #8d8d8d; border-width: 0px 1px 1px 0px; }";
         $content.=" </style></head><body>";
         $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                     $LANG['common'][37].":</span> ".$u->getName()."<br>";
         $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                     $LANG['mailing'][7]."</span> ".$name."<br>";
         if (!empty($tech)) {
            $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                        $LANG['common'][10].":</span> ".$tech."<br>";
         }
         $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                     $LANG['search'][8].":</span> ".convDateTime($this->fields["begin"])."<br>";
         $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                     $LANG['search'][9].":</span> ".convDateTime($this->fields["end"])."<br>";
         $content.="<span style='color:#8B8C8F; font-weight:bold;  text-decoration:underline; '>".
                     $LANG['common'][25].":</span> ".nl2br($this->fields["comment"])."<br>";
      } else { // text format
         $content.=$LANG['mailing'][1]."\n";
         $content.=$LANG['common'][37]."&nbsp;: ".$u->getName()."\n";
         $content.=$LANG['mailing'][7]."&nbsp;: ".$name."\n";
         if (!empty($tech)) {
            $content.= $LANG['common'][10]."&nbsp;: ".$tech."\n";
         }
         $content.=$LANG['search'][8]."&nbsp;: ".convDateTime($this->fields["begin"])."\n";
         $content.=$LANG['search'][9]."&nbsp;: ".convDateTime($this->fields["end"])."\n";
         $content.=$LANG['common'][25]."&nbsp;: ".$this->fields["comment"]."\n";
         $content.=$LANG['mailing'][1]."\n";
      }
      return $content;
   }

   function can($ID,$right,&$input=NULL) {
      if (empty($ID) || $ID<=0) {
         // Add reservation - TODO should also check commonitem->can(r)
         return haveRight("reservation_helpdesk","1");
      }
      if (!isset($this->fields['id']) || $this->fields['id']!=$ID) {
         // Item not found : no right
         if (!$this->getFromDB($ID)) {
            return false;
         }
      }
      // Original user always have right
      if ($this->fields['users_id']==$_SESSION['glpiID']) {
         return true;
      }
      if (!haveRight("reservation_central",$right)) {
         return false;
      }
      $ri=new ReservationItem();
      if (!$ri->getFromDB($this->fields["reservationitems_id"])) {
         return false;
      }
      if (!class_exists($ri->fields["itemtype"])) {
         return false;
      }
      $item = new $ri->fields["itemtype"]();
      if (!$item->getFromDB($ri->fields["items_id"])) {
         return false;
      }
      return haveAccessToEntity($item->getEntityID());
   }
}

?>
