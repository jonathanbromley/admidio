<?php
/******************************************************************************
 * Script pr�ft, ob man eingeloggt ist und setzt die Zeitstempel neu
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

// Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
	$g_tbl_praefix = "adm";

// Defines fuer alle Datenbanktabellen
define("TBL_ANNOUNCEMENTS", $g_tbl_praefix. "_ankuendigungen");
define("TBL_PHOTOS", $g_tbl_praefix. "_photo");
define("TBL_ORGANIZATIONS", $g_tbl_praefix. "_gruppierung");
define("TBL_MEMBERS", $g_tbl_praefix. "_mitglieder");
define("TBL_NEW_USER", $g_tbl_praefix. "_new_user");
define("TBL_ROLES", $g_tbl_praefix. "_rolle");
define("TBL_SESSIONS", $g_tbl_praefix. "_session");
define("TBL_DATES", $g_tbl_praefix. "_termine");
define("TBL_USERS", $g_tbl_praefix. "_user");
define("TBL_USER_DATA", $g_tbl_praefix. "_user_data");
define("TBL_USER_FIELDS", $g_tbl_praefix. "_user_field");
define("TBL_ROLE_TYPES", $g_tbl_praefix. "_role_types");

// Verbindung zu Datenbank herstellen
$g_adm_con = mysql_connect ($g_adm_srv, $g_adm_usr, $g_adm_pw);
mysql_select_db($g_adm_db, $g_adm_con );

// Verbindung zur Forum-Datenbank herstellen
if($g_forum)
   $g_forum_con = mysql_connect ($g_forum_srv, $g_forum_usr, $g_forum_pw);
else
   $g_forum_con;

// Globale Variablen
$g_session_id    = "";
$g_user_id       = 0;
$g_nickname      = "";
$g_session_valid = 0;

// Cookies einlesen
if(isset($_COOKIE["". TBL_SESSIONS. ""]))
   $g_session_id = $_COOKIE["". TBL_SESSIONS. ""];
else
   $g_session_id = "";

if(isset($_COOKIE["". TBL_USERS. "_id"]))
   $g_user_id = $_COOKIE["". TBL_USERS. "_id"];
else
   $g_user_id = 0;

if(isset($_COOKIE["adm_login"]))
   $g_nickname = $_COOKIE["adm_login"];
else
   $g_nickname = "";

// Daten der Gruppierung in Variable einlesen
$sql    = "SELECT * FROM ". TBL_ORGANIZATIONS. "
            WHERE ag_shortname LIKE '$g_organization' ";
$sql    = prepareSQL($sql, array($g_session_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$row = mysql_fetch_object($result);
$g_orga_property = array(
	'ag_id'          => $row->ag_id,
	'ag_longname'    => $row->ag_longname,
	'ag_shortname'   => $row->ag_shortname,
	'ag_mother'      => $row->ag_mother,
	'ag_bbcode'      => $row->ag_bbcode,
	'ag_mail_extern' => $row->ag_mail_extern,
	'ag_homepage'    => $row->ag_homepage,
	'ag_mail_attachment_size' => $row->ag_mail_attachment_size,
	'ag_enable_rss' => $row->ag_enable_rss
	);

if ($g_session_id != "")
{
   // Session auf Gueltigkeit pruefen

   $sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE as_session LIKE {0}";
   $sql    = prepareSQL($sql, array($g_session_id));
   $result = mysql_query($sql, $g_adm_con);

   db_error($result);

   $session_found = mysql_num_rows($result);
   $row           = mysql_fetch_object($result);

   if ($session_found == 1)
   {
      $valid    = false;
      $time_gap = time() - mysqlmaketimestamp($row->as_datetime);

      if($row->as_long_login == 1)
      {
         // User will erst nach 10 Stunden ausgeloggt werden
         if ($time_gap < 28800) $valid = true;
      }
      else
      {
         // wenn l�nger als 30 min. nichts gemacht, dann ausloggen
         if ($time_gap < 1800) $valid = true;
      }

      if($valid)
      {
         $g_session_valid = 1;

         // Datetime der Session muss aktualisiert werden

         $act_datetime   = date("Y-m-d H:i:s", time());

         $sql    = "UPDATE ". TBL_SESSIONS. " SET as_datetime = '$act_datetime' WHERE as_session LIKE {0}";
         $sql    = prepareSQL($sql, array($g_session_id));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);

         $g_user_id = $row->as_au_id;
      }
      else
      {
         // User war zu lange inaktiv -> Session loeschen

         $g_user_id       = 0;
         $g_nickname      = "";

         $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE as_session LIKE {0}";
         $sql    = prepareSQL($sql, array($g_session_id));
         $result = mysql_query($sql, $g_adm_con);

         db_error($result);
      }
   }
   else
   {
      if ($session_found != 0)
      {
         // ID mehrfach vergeben -> Fehler und IDs loeschen

         $g_user_id       = 0;
         $g_nickname      = "";

         $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE as_session LIKE {0}";
         $sql    = prepareSQL($sql, array($g_session_id));
         $result = mysql_query($sql, $g_adm_con);

         db_error($result);
      }

      $g_user_id       = 0;
      $g_nickname      = "";
   }
}

?>