<?php

/*
=====================================================
Protected Links
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011-2013 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: upd.protected_links.php
-----------------------------------------------------
 Purpose: Encrypt and protect download links
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'protected_links/config.php';

class Protected_links_upd {

    var $version = PROTECTED_LINKS_ADDON_VERSION;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
  
        $this->EE->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if ($this->EE->db->field_exists('settings', 'modules') == FALSE)
		{
			$this->EE->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array(
            's3_key_id' => '',
            's3_key_value' => '',
            'rackspace_api_login' => '',
            'rackspace_api_password' => '',
            'cloudfront_key_pair_id' => '',
            'cloudfront_private_key' => ''
        );
        $data = array( 'module_name' => 'Protected_links' , 'module_version' => $this->version, 'has_cp_backend' => 'y', 'settings'=> serialize($settings) ); 
        $this->EE->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Protected_links' , 'method' => 'process' ); 
        $this->EE->db->insert('actions', $data); 
        
        $sql[] = "CREATE TABLE  `exp_protected_links_files` (
                    `file_id` INT NOT NULL AUTO_INCREMENT ,
                    `storage` VARCHAR( 20 )  default 'url' ,
                    `endpoint` VARCHAR( 255 ) NOT NULL default '',
                    `container` VARCHAR( 255 ) NOT NULL default '',
                    `url` VARCHAR( 255 ) NOT NULL ,
                    `dl_count` INT NOT NULL default '0',
                    `dl_date` INT NULL ,
                        PRIMARY KEY  (`file_id`),
                        INDEX (`storage`),
                        INDEX (`container`),
                        INDEX (`endpoint`),
                        INDEX (`url`)
                    )";
        
        $sql[] = "CREATE TABLE  `exp_protected_links_links` (
                    `link_id` INT NOT NULL AUTO_INCREMENT ,
                    `accesskey` CHAR( 16 ) NULL,
                    `file_id` INT NOT NULL ,
                    `filename` VARCHAR( 255 ) NOT NULL, 
                    `title` VARCHAR( 255 ) NULL ,
                    `description` TEXT NULL,
                    `type` varchar(100) default 'application/force-download',
                    `guest_access` ENUM(  'y',  'n' ) NOT NULL default 'y',
                    `bind_ip` VARCHAR( 100 ) NULL ,
                    `deny_hotlink` ENUM(  'y',  'n' ) NOT NULL default 'n',
                    `inline` ENUM(  'y',  'n' ) NOT NULL default 'n',
                    `group_access` VARCHAR( 100 ) NULL ,
                    `member_access` VARCHAR( 255 ) NULL,
                    `link_date` INT NOT NULL,
                    `expires` INT NULL ,
                    `member_limit` INT NULL ,
                    `custom_access_rules` TEXT NULL ,
                    `use_backend` ENUM(  'y',  'n' ) NOT NULL default 'n',
                    `cp_generated` ENUM(  'y',  'n' ) NOT NULL default 'n',
                    `dl_count` INT NOT NULL default '0',
                        PRIMARY KEY  (`link_id`),
                        UNIQUE KEY `accesskey` (`accesskey`),
                        INDEX (`file_id`)
                    )";
        
        $sql[] = "CREATE TABLE  `exp_protected_links_stats` (
                    `dl_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                    `link_id` INT NOT NULL,
                    `file_id` INT NOT NULL,
                    `member_id` INT NOT NULL ,
                    `ip` VARCHAR( 100 ) NOT NULL ,
                    `dl_date` INT NOT NULL ,
                        INDEX (  `link_id` ),
                        INDEX (  `member_id` )
                    )";
                    
        foreach ($sql as $qstr)
        {
            $this->EE->db->query($qstr);
        }

        return TRUE; 
        
    } 
    
    function uninstall() { 

        $this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Protected_links')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Protected_links'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Protected_links'); 
        $this->EE->db->delete('actions'); 
        
        $this->EE->db->query("DROP TABLE exp_protected_links_files");
        $this->EE->db->query("DROP TABLE exp_protected_links_links");
        $this->EE->db->query("DROP TABLE exp_protected_links_stats");
        
        return TRUE; 
    } 
    
    function update($current='') { 
        if ($current < 1.3) { 
            
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` CHANGE `storage` `storage` VARCHAR( 20 ) NULL DEFAULT 'url'");
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` CHANGE `container` `container` VARCHAR( 255 ) NOT NULL");
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` CHANGE `url` `url` VARCHAR( 255 ) NOT NULL ");
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` CHANGE `dl_date` `dl_date` INT( 11 ) NULL ");
            
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` ADD INDEX ( `storage` )");
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` ADD INDEX ( `container` )");
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` ADD INDEX ( `url` )");

            //do a database cleanup due to previous bug that caused too many records in files table
            $q = $this->EE->db->query("SELECT file_id, `storage`, container, url, SUM(dl_count) as dl_count, dl_date FROM `exp_protected_links_files` group by `storage`, container, url order by dl_date desc");
            $files_to_stay = array();
            foreach ($q->result_array() as $row)
            {
                $files_to_stay[] = $row['file_id'];
                $dl_date = $row['dl_date'];
                
                $this->EE->db->select('file_id, dl_date');
                $this->EE->db->from('protected_links_files');
                $this->EE->db->where('storage', "{$row['storage']}");
                $this->EE->db->where('container', "{$row['container']}");
                $this->EE->db->where('url', "{$row['url']}");
                $subq = $this->EE->db->get();
                
                $upd_data['file_id'] = $row['file_id'];
                foreach ($subq->result() as $obj)
                {
                    if (intval($obj->dl_date) > intval($dl_date)) $dl_date = $obj->dl_date;
                    
                    $this->EE->db->where('file_id', $obj->file_id);
                    $this->EE->db->update('protected_links_links', $upd_data);
                    
                    $this->EE->db->where('file_id', $obj->file_id);
                    $this->EE->db->update('protected_links_stats', $upd_data);
                }
                $row['dl_date'] = $dl_date;
                $this->EE->db->where('file_id', $row['file_id']);
                $this->EE->db->update('protected_links_files', $row);
            }
            if (!empty($files_to_stay))
            {
                $this->EE->db->where_not_in('file_id', $files_to_stay);
                $this->EE->db->delete('protected_links_files');
            }
            
        } 
        if ($current < 1.4) { 
            $this->EE->db->query("ALTER TABLE `exp_protected_links_links` ADD COLUMN `deny_hotlink` ENUM(  'y',  'n' ) NOT NULL default 'n'");
        } 
        if ($current < 1.5) { 
            $this->EE->db->query("ALTER TABLE `exp_protected_links_links` ADD COLUMN `inline` ENUM(  'y',  'n' ) NOT NULL default 'n'");
        } 

		if ($this->EE->db->field_exists('inline', 'protected_links_links') == FALSE)
		{
			$this->EE->db->query("ALTER TABLE `exp_protected_links_links` ADD COLUMN `inline` ENUM(  'y',  'n' ) NOT NULL default 'n'");
		}
        
        if ($current < 1.8) { 
            $this->EE->db->query("ALTER TABLE `exp_protected_links_links` ADD COLUMN `description` TEXT NOT NULL default ''");
        } 
        
        if ($current < 1.9) { 
            $this->EE->db->query("ALTER TABLE `exp_protected_links_files` ADD COLUMN `endpoint` VARCHAR(255) NOT NULL default ''");
        } 
        return TRUE; 
    } 
	

}
/* END */
?>