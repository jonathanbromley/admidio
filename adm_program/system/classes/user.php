<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getProperty($field_name, $property) 
 *                      - gibt den Inhalt einer Eigenschaft eines Feldes zurueck.
 *                        Dies kann die usf_id, usf_type, cat_id, cat_name usw. sein
 * getVCard()           - Es wird eine vCard des Users als String zurueckgegeben
 * viewProfile          - Ueberprueft ob der User das Profil eines uebrgebenen
 *                        Users einsehen darf
 * viewRole             - Ueberprueft ob der User eine uebergebene Rolle(Liste)
 *                        einsehen darf
 * isWebmaster()        - gibt true/false zurueck, falls der User Mitglied der 
 *                        Rolle "Webmaster" ist
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class User extends TableAccess
{
    var $webmaster;
    var $b_set_last_change;         // Kennzeichen, ob User und Zeitstempel der aktuellen Aenderung gespeichert werden sollen
    var $real_password;             // Unverschluesseltes Passwort. Ist nur gefuellt, wenn gerade das Passwort gesetzt wurde
    
    var $db_user_fields = array();  // Array ueber alle Felder der User-Fields-Tabelle des entsprechenden Users
    var $roles_rights   = array();  // Array ueber alle Rollenrechte mit dem entsprechenden Status des Users
    var $list_view_rights = array();  // Array ueber Listenrechte einzelner Rollen
    
    // Konstruktor
    function User(&$db, $user_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USERS;
        $this->column_praefix = "usr";
        
        if(strlen($user_id) > 0)
        {
            $this->readData($user_id);
        }
        else
        {
            $this->clear();
        }
    }

    // User mit der uebergebenen ID aus der Datenbank auslesen
    function readData($user_id)
    {
        parent::readData($user_id);
                    
        // user_data-Array aufbauen
        $this->fillUserFieldArray($user_id);
    }
    
    function fillUserFieldArray($user_id = 0)
    {
        global $g_current_organization;
        
        // erst mal alles bisherige entfernen
        $this->db_user_fields = array();
        
        if(is_numeric($user_id) && $user_id > 0)
        {        
            $field_usd_value = "usd_value";
            $join_user_data  = "LEFT JOIN ". TBL_USER_DATA. "
                                  ON usd_usf_id = usf_id
                                 AND usd_usr_id = $user_id";
        }
        else
        {
            $field_usd_value = "NULL as usd_value";
            $join_user_data  = "";
        }
        
        // Daten aus adm_user_data auslesen
        $sql = "SELECT usf_id, cat_id, cat_name, usf_name, usf_type, usf_description, 
                       usf_disabled, usf_hidden, usf_mandatory, usf_system, $field_usd_value
                  FROM ". TBL_CATEGORIES. ", ". TBL_USER_FIELDS. "
                       $join_user_data
                 WHERE usf_cat_id = cat_id 
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = ". $g_current_organization->getValue("org_id"). " )
                 ORDER BY cat_sequence, usf_sequence";
        $result_usf = $this->db->query($sql);

        while($row_usf = $this->db->fetch_array($result_usf))
        {
            // ein mehrdimensionales Array aufbauen, welche fuer jedes usf-Feld alle 
            // Daten des Sql-Statements beinhaltet
            for($i = 0; $i < $this->db->num_fields($result_usf); $i++)
            {
                $this->db_user_fields[$row_usf['usf_name']][$this->db->field_name($result_usf, $i)] = $row_usf[$i];
            }
            // Flag, ob der Inhalt geaendert wurde, um das Update effektiver zu gestalten
            $this->db_user_fields[$row_usf['usf_name']]['changed'] = false;
            // Flag, welches angibt, ob der Wert neu hinzugefuegt wurde
            if(is_null($row_usf['usd_value']))
            {
                $this->db_user_fields[$row_usf['usf_name']]['new'] = true;
            }
            else
            {
                $this->db_user_fields[$row_usf['usf_name']]['new'] = false;
            }
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    // die Methode wird innerhalb von clear() aufgerufen
    function clear()
    {
        parent::clear();

        $this->webmaster = 0;
        $this->b_set_last_change = true;
        
        // neue User sollten i.d.R. auf valid stehen (Ausnahme Registrierung)
        $this->setValue("usr_valid", 1);
        
        // user_data-Array komplett neu aufbauen
        // vorher wurde nur alles geleert, dadurch aber keine geloeschten Felder entfernt
        $this->fillUserFieldArray();
        
        // Arrays initialisieren
        $this->roles_rights = array();
        $this->list_view_rights = array();
    }

    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Methode wird innerhalb von setValue() aufgerufen
    function setValue($field_name, $field_value)
    {        
        if(strpos($field_name, "usr_") !== 0)
        {
            // Daten fuer User-Fields-Tabelle
            if($field_value != $this->db_user_fields[$field_name]['usd_value'])
            {
                if(strlen($this->db_user_fields[$field_name]['usd_value']) == 0)
                {
                    $this->db_user_fields[$field_name]['new'] = true;
                }
                else
                {
                    $this->db_user_fields[$field_name]['new'] = false;
                }
                
                // Homepage noch mit http vorbelegen
                if($this->getProperty($field_name, "usf_type") == "URL")
                {
                    if(strpos(strtolower($field_value), "http://")  === false
                    && strpos(strtolower($field_value), "https://") === false )
                    {
                        $field_value = "http://". $field_value;
                    }
                }
                $this->db_user_fields[$field_name]['usd_value'] = $field_value;
                $this->db_user_fields[$field_name]['changed']   = true;
            }
        }
        elseif($field_name == "usr_password")
        {
            // Passwort verschluesselt und unverschluesselt speichern
            $this->real_password = $field_value;
            $field_value = md5($field_value);
        }
        parent::setValue($field_name, $field_value);
    }
    
    // Methode prueft, ob evtl. ein Wert aus der User-Fields-Tabelle
    // angefordert wurde und gibt diesen zurueck
    // die Funktion wird innerhalb von getValue() aufgerufen
    function getValue($field_name)
    {
        if(strpos($field_name, "usr_") === 0)
        {
            $field_value = parent::getValue($field_name);
            
            // ist die Create-Id leer, so wurde der Datensatz durch Registierung angelegt und gehoert dem User selber
            if($field_name == "usr_usr_id_create" && strlen($field_value) == 0)
            {
                $field_value = parent::getValue("usr_id");
            }
            return $field_value;
        }
        else
        {
            return htmlspecialchars($this->getProperty($field_name, "usd_value"), ENT_QUOTES);
        }
    }    

    // Methode gibt den Wert eines Profilfeldes zurueck
    // Property ist dabei ein Feldname aus der Tabelle adm_user_fields oder adm_user_data
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getProperty($field_name, $property)
    {
        return $this->db_user_fields[$field_name][$property];
    }    
    
    // aehnlich getProperty, allerdings suche ueber usf_id
    function getPropertyById($field_id, $property)
    {
        foreach($this->db_user_fields as $key => $value)
        {
            if($value['usf_id'] == $field_id)
            {
                return $value[$property];
            }
        }
        return false;
    } 
        
    // die Funktion speichert die Userdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        global $g_current_session, $g_current_user;
        $fields_changed = $this->db_fields_changed;
        
        if($this->b_set_last_change)
        {
            if($this->new_record)
            {
                $this->setValue("usr_timestamp_create", date("Y-m-d H:i:s", time()));
                $this->setValue("usr_usr_id_create", $g_current_user->getValue("usr_id"));
            }
            else
            {
                // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
                if(time() > (strtotime($this->getValue("usr_timestamp_create")) + 900)
                || $g_current_user->getValue("usr_id") != $this->getValue("usr_usr_id_create") )
                {
                    $this->setValue("usr_timestamp_change", date("Y-m-d H:i:s", time()));
                    $this->setValue("usr_usr_id_change", $g_current_user->getValue("usr_id"));
                }
            }
        }

        $this->b_set_last_change = true;
        parent::save();
        
        // nun noch Updates fuer alle geaenderten User-Fields machen
        foreach($this->db_user_fields as $key => $value)
        {
            if($value['changed'] == true)
            {
                $item_connection = "";
                $sql_field_list  = "";                

                if(strlen($value['usd_value']) == 0)
                {
                    $sql = "DELETE FROM ". TBL_USER_DATA. " 
                             WHERE usd_usr_id = ". $this->db_fields['usr_id']. "
                               AND usd_usf_id = ". $value['usf_id'];
                }
                else
                {
                    if($value['new'] == true)
                    {
                        $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value) 
                                VALUES (". $this->db_fields['usr_id']. ", ". $value['usf_id']. ", '". $value['usd_value']. "') ";
                        $this->db_user_fields[$key]['new'] = false;
                    }
                    else
                    {
                        $sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = '". $value['usd_value']. "'
                                 WHERE usd_usr_id = ". $this->db_fields['usr_id']. "
                                   AND usd_usf_id = ". $value['usf_id'];
                    }
                }
                $result = $this->db->query($sql);
                $this->db_user_fields[$key]['changed'] = false;
            }
        }
        
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl. 
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
        }           
    }

    // Referenzen zum aktuellen Benutzer loeschen
    // die Methode wird innerhalb von delete() aufgerufen
    function delete()
    {
        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id_create = NULL
                    WHERE ann_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id_change = NULL
                    WHERE ann_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id_create = NULL
                    WHERE dat_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id_change = NULL
                    WHERE dat_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_FOLDERS. " SET fol_usr_id = NULL
                    WHERE fol_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_FILES. " SET fil_usr_id = NULL
                    WHERE fil_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id = NULL
                    WHERE gbo_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_LINKS. " SET lnk_usr_id_create = NULL
                    WHERE lnk_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_LINKS. " SET lnk_usr_id_change = NULL
                    WHERE lnk_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_create = NULL
                    WHERE pho_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_change = NULL
                    WHERE pho_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ROLES. " SET rol_usr_id_create = NULL
                    WHERE rol_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ROLES. " SET rol_usr_id_change = NULL
                    WHERE rol_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ROLE_DEPENDENCIES. " SET rld_usr_id = NULL
                    WHERE rld_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_USERS. " SET usr_usr_id_create = NULL
                    WHERE usr_usr_id_create = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_USERS. " SET usr_usr_id_change = NULL
                    WHERE usr_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_MEMBERS. " WHERE mem_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_AUTO_LOGIN. " WHERE atl_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);
        
        $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_USER_DATA. " WHERE usd_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);
        
        return parent::delete();
    }

    // gibt die Userdaten als VCard zurueck
    // da das Windows-Adressbuch einschliesslich XP kein UTF8 verarbeiten kann, alles in ISO-8859-1 ausgeben
    function getVCard()
    {
        global $g_current_user;

        $editAllUsers = $g_current_user->editProfile($this->db_fields['usr_id']);
        
        $vcard  = (string) "BEGIN:VCARD\r\n";
        $vcard .= (string) "VERSION:2.1\r\n";
        if($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Vorname']['usf_hidden'] == 0))
        {
            $vcard .= (string) "N;CHARSET=ISO-8859-1:" . utf8_decode($this->getValue("Nachname")). ";". utf8_decode($this->getValue("Vorname")) . ";;;\r\n";
        }
        if($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Nachname']['usf_hidden'] == 0))
        {
            $vcard .= (string) "FN;CHARSET=ISO-8859-1:". utf8_decode($this->getValue("Vorname")) . " ". utf8_decode($this->getValue("Nachname")) . "\r\n";
        }
        if (strlen($this->getValue("usr_login_name")) > 0)
        {
            $vcard .= (string) "NICKNAME;CHARSET=ISO-8859-1:" . utf8_decode($this->getValue("usr_login_name")). "\r\n";
        }
        if (strlen($this->getValue("Telefon")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Telefon']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "TEL;HOME;VOICE:" . $this->getValue("Telefon"). "\r\n";
        }
        if (strlen($this->getValue("Handy")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Handy']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "TEL;CELL;VOICE:" . $this->getValue("Handy"). "\r\n";
        }
        if (strlen($this->getValue("Fax")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Fax']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "TEL;HOME;FAX:" . $this->getValue("Fax"). "\r\n";
        }
        if($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Adresse']['usf_hidden'] == 0 && $this->db_user_fields['Ort']['usf_hidden'] == 0
        && $this->db_user_fields['PLZ']['usf_hidden'] == 0  && $this->db_user_fields['Land']['usf_hidden'] == 0))
        {
            $vcard .= (string) "ADR;CHARSET=ISO-8859-1;HOME:;;" . utf8_decode($this->getValue("Adresse")). ";" . utf8_decode($this->getValue("Ort")). ";;" . utf8_decode($this->getValue("PLZ")). ";" . utf8_decode($this->getValue("Land")). "\r\n";
        }
        if (strlen($this->getValue("Homepage")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Homepage']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "URL;HOME:" . $this->getValue("Homepage"). "\r\n";
        }
        if (strlen($this->getValue("Geburtstag")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Geburtstag']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "BDAY:" . mysqldatetime("ymd", $this->getValue("Geburtstag")) . "\r\n";
        }
        if (strlen($this->getValue("E-Mail")) > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['E-Mail']['usf_hidden'] == 0)))
        {
            $vcard .= (string) "EMAIL;PREF;INTERNET:" . $this->getValue("E-Mail"). "\r\n";
        }
        if (strlen($this->getValue("usr_photo")) > 0)
        {
            $vcard .= (string) "PHOTO;ENCODING=BASE64;TYPE=JPEG:".base64_encode($this->getValue("usr_photo")). "\r\n";
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($this->getValue("Geschlecht") > 0 
        && ($editAllUsers || ($editAllUsers == false && $this->db_user_fields['Geschlecht']['usf_hidden'] == 0)))
        {
            if($this->getValue("Geschlecht") == 1)
            {
                $wab_gender = 2;
            }
            else
            {
                $wab_gender = 1;
            }
            $vcard .= (string) "X-WAB-GENDER:" . $wab_gender . "\r\n";
        }
        if (strlen($this->getValue("usr_timestamp_change")) > 0)
        {
            $vcard .= (string) "REV:" . mysqldatetime("ymdThis", $this->getValue("usr_timestamp_change")) . "\r\n";
        }

        $vcard .= (string) "END:VCARD\r\n";
        return $vcard;
    }
    
    // Methode prueft, ob der User das uebergebene Rollenrecht besitzt und setzt das Array mit den Flags, 
    // welche Rollen der User einsehen darf
    function checkRolesRight($right = "")
    {
        if($this->db_fields['usr_id'] > 0)
        {
            if(count($this->roles_rights) == 0)
            {
                global $g_current_organization;
                $tmp_roles_rights  = array("rol_assign_roles" => "0", "rol_approve_users" => "0", 
                                            "rol_announcements" => "0", "rol_dates" => "0", 
                                            "rol_download" => "0", "rol_edit_user" => "0", 
                                            "rol_guestbook" => "0", "rol_guestbook_comments" => "0", 
                                            "rol_mail_logout" => "0", "rol_mail_login" => "0", 
                                            "rol_photo" => "0", "rol_profile" => "0", 
                                            "rol_weblinks" => "0", "rol_all_lists_view" => "0");

                // Alle Rollen der Organisation einlesen und ggf. Mitgliedschaft dazu joinen
                $sql    = "SELECT *
                             FROM ". TBL_CATEGORIES. ", ". TBL_ROLES. "
                             LEFT JOIN ". TBL_MEMBERS. "
                               ON mem_usr_id = ". $this->db_fields['usr_id']. "
                              AND mem_rol_id = rol_id
                              AND mem_valid  = 1
                            WHERE rol_valid  = 1 
                              AND rol_cat_id = cat_id
                              AND cat_org_id = ". $g_current_organization->getValue("org_id");
                $this->db->query($sql);
                
                while($row = $this->db->fetch_array())
                {
                    // Rechte nur beruecksichtigen, wenn auch Rollenmitglied
                    if($row['mem_usr_id'] > 0)
                    {
                        // Rechte der Rollen in das Array uebertragen, 
                        // falls diese noch nicht durch andere Rollen gesetzt wurden
                        foreach($tmp_roles_rights as $key => $value)
                        {
                            if($value == "0" && $row[$key] == "1")
                            {
                                $tmp_roles_rights[$key] = "1";
                            }
                        }
                    }
                    
                    // Webmasterflag setzen
                    if($row['mem_usr_id'] > 0 && $row['rol_name'] == "Webmaster")
                    {
                        $this->webmaster = 1;
                    }
                    
                    // Listenansichtseinstellung merken
                    
                    // Leiter duerfen die Rolle sehen
                    if($row['mem_usr_id'] > 0 && ($row['rol_this_list_view'] > 0 || $row['mem_leader'] == 1))
                    {
                        // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    elseif($row['rol_this_list_view'] == 2)
                    {
                        // andere Rollen anschauen, wenn jeder sie sehen darf
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    else
                    {
                        $this->list_view_rights[$row['rol_id']] = 0;
                    }
                }
                $this->roles_rights = $tmp_roles_rights;
                
                // ist das Recht "alle Listen einsehen" gesetzt, dann dies auch im Array bei allen Rollen setzen
                if($this->roles_rights['rol_all_lists_view'])
                {
                    foreach($this->list_view_rights as $key => $value)
                    {
                        $this->list_view_rights[$key] = 1;
                    }
                }
            }

            if(strlen($right) == 0 || $this->roles_rights[$right] == 1)
            {
                return true;
            }
        }
        return 0;
    }

    // Funktion prueft, ob der angemeldete User Ankuendigungen anlegen und bearbeiten darf
    function editAnnouncements()
    {
        return $this->checkRolesRight('rol_announcements');
    }

    // Funktion prueft, ob der angemeldete User Registrierungen bearbeiten und zuordnen darf
    function approveUsers()
    {
        return $this->checkRolesRight('rol_approve_users');
    }

    // Funktion prueft, ob der angemeldete User Rollen zuordnen, anlegen und bearbeiten darf
    function assignRoles()
    {
        return $this->checkRolesRight('rol_assign_roles');
    }

    //Ueberprueft ob der User das Recht besitzt, alle Rollenlisten einsehen zu duerfen 
    function viewAllLists()
    {
        return $this->checkRolesRight('rol_all_lists_view'); 
    }
    
    // Funktion prueft, ob der angemeldete User Termine anlegen und bearbeiten darf
    function editDates()
    {
        return $this->checkRolesRight('rol_dates');
    }
    
    // Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf
    function editDownloadRight()
    {
        return $this->checkRolesRight('rol_download');
    }
    
    // Funktion prueft, ob der angemeldete User das entsprechende Profil bearbeiten darf
    function editProfile($profileID = NULL)
    {
        if($profileID == NULL)
        {
            $profileID = $this->db_fields['usr_id'];
        }

        //soll das eigene Profil bearbeitet werden?
        if($profileID == $this->db_fields['usr_id'] && $this->db_fields['usr_id'] > 0)
        {
            $edit_profile = $this->checkRolesRight('rol_profile');

            if($edit_profile == 1)
            {
                return true;
            }
            else
            {
                return $this->editUsers();
            }

        }
        else
        {
            return $this->editUsers();
        }
    }

    // Funktion prueft, ob der angemeldete User fremde Benutzerdaten bearbeiten darf
    function editUsers()
    {
        return $this->checkRolesRight('rol_edit_user');
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege loeschen und editieren darf
    function editGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook');
    }
    
    // Funktion prueft, ob der angemeldete User Gaestebucheintraege kommentieren darf
    function commentGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook_comments');
    }
    
    // Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf    
    function editPhotoRight()
    {
        return $this->checkRolesRight('rol_photo');
    }

    // Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
    function editWeblinksRight()
    {
        return $this->checkRolesRight('rol_weblinks');
    }
    
    // Funktion prueft, ob der User ein Profil einsehen darf    
    function viewProfile($usr_id)
    {
        global $g_current_organization;
        $view_profile = false;
        
        //Hat ein User Profileedit rechte, darf er es natuerlich auch sehen
        if($this->editProfile($usr_id))
        {
            $view_profile = true;
        }
        else
        {
            // Benutzer, die alle Listen einsehen duerfen, koennen auch alle Profile sehen
            if($this->viewAllLists())
            {
                $view_profile = true;
            }
            else
            {
                $sql    = "SELECT rol_id, rol_this_list_view
                             FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                            WHERE mem_usr_id = ".$usr_id. "
                              AND mem_valid  = 1
                              AND mem_rol_id = rol_id
                              AND rol_valid  = 1 
                              AND rol_cat_id = cat_id
                              AND cat_org_id = ". $g_current_organization->getValue("org_id");
                $this->db->query($sql);
                
                if($this->db->num_rows() > 0)
                {             
                    while($row = $this->db->fetch_array())
                    {
                        if($row['rol_this_list_view'] == 2)
                        {
                            // alle angemeldeten Benutzer duerfen Rollenlisten/-profile sehen
                            $view_profile = true;
                        }
                        elseif($row['rol_this_list_view'] == 1
                        && isset($this->list_view_rights[$row['rol_id']]))
                        {
                            // nur Rollenmitglieder duerfen Rollenlisten/-profile sehen
                            $view_profile = true;
                        }
                    }
                }
            }
        }
        return $view_profile;
    }
    
    // Methode prueft, ob der angemeldete User eine bestimmte oder alle Listen einsehen darf    
    function viewRole($rol_id)
    {
        $view_role = false;
        // Abfrage ob der User durch irgendeine Rolle das Recht bekommt alle Listen einzusehen
        if($this->viewAllLists())
        {
            $view_role = true;
        }
        else
        {
            // Falls er das Recht nicht hat Kontrolle ob fuer eine bestimmte Rolle
            if(isset($this->list_view_rights[$rol_id]) && $this->list_view_rights[$rol_id] > 0)
            {
                $view_role = true;
            }
        }
        return $view_role;
    }

    // Methode liefert true zurueck, wenn der User Mitglied der Rolle "Webmaster" ist
    function isWebmaster()
    {
        $this->checkRolesRight();
        return $this->webmaster;
    }
}
?>