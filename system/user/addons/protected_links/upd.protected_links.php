<?php

/*
=====================================================
Protected Links
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011-2016 Yuri Salimovskiy
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

    } 
    
    function install() { 
  
        ee()->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if (ee()->db->field_exists('settings', 'modules') == FALSE)
		{
			ee()->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array(
            's3_key_id' => '',
            's3_key_value' => '',
            'rackspace_api_login' => '',
            'rackspace_api_password' => '',
            'cloudfront_key_pair_id' => '',
            'cloudfront_private_key' => ''
        );
        $data = array( 'module_name' => 'Protected_links' , 'module_version' => $this->version, 'has_cp_backend' => 'y', 'settings'=> base64_encode(serialize($settings))); 
        ee()->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Protected_links' , 'method' => 'process' ); 
        ee()->db->insert('actions', $data); 
        
        //protected_links_files
		$fields = array(
			'file_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
            'storage'		=> array('type' => 'VARCHAR',	'constraint'=> 20,	'default' => ''),
            'endpoint'		=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => ''),
            'container'		=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => ''),
            'url'		    => array('type' => 'VARCHAR',	'constraint'=> 255),
			'dl_count'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'dl_date'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0)
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('file_id', TRUE);
        ee()->dbforge->add_key('storage');
        ee()->dbforge->add_key('container');
        ee()->dbforge->add_key('endpoint');
        ee()->dbforge->add_key('url');
		ee()->dbforge->create_table('protected_links_files', TRUE);
        
        //protected_links_links
		$fields = array(
			'link_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
            'accesskey'		=> array('type' => 'CHAR',     	'constraint'=> 16),
            'file_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'filename'		=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => ''),
            'title'	    	=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => ''),
            'description'   => array('type' => 'TEXT'),
            'type'		    => array('type' => 'VARCHAR',	'constraint'=> 100,	'default' => 'application/force-download'),
            'guest_access'  => array('type' => 'ENUM',		'constraint'=> "'y','n'",	'default' => 'y'),
            'bind_ip'	    => array('type' => 'VARCHAR',	'constraint'=> 50),
            'deny_hotlink'  => array('type' => 'ENUM',		'constraint'=> "'y','n'",	'default' => 'n'),
            'inline'        => array('type' => 'ENUM',		'constraint'=> "'y','n'",	'default' => 'n'),
            'group_access'	=> array('type' => 'VARCHAR',	'constraint'=> 100),
            'member_access'	=> array('type' => 'VARCHAR',	'constraint'=> 255),
			'link_date'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'expires'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'member_limit'	=> array('type' => 'INT',		'unsigned' => TRUE),
            'custom_access_rules'   => array('type' => 'TEXT'),
            'use_backend'  => array('type' => 'ENUM',		'constraint'=> "'y','n'",	'default' => 'n'),
            'cp_generated'        => array('type' => 'ENUM',		'constraint'=> "'y','n'",	'default' => 'n'),
            'dl_count'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0)
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('link_id', TRUE);
        ee()->dbforge->add_key('accesskey');
        ee()->dbforge->add_key('file_id');
		ee()->dbforge->create_table('protected_links_links', TRUE);
        
        //protected_links_stats
		$fields = array(
			'dl_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
            'link_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'file_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'member_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'ip'		    => array('type' => 'VARCHAR',	'constraint'=> 50,	'default' => ''),
            'dl_date'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0)
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('dl_id', TRUE);
        ee()->dbforge->add_key('link_id');
        ee()->dbforge->add_key('file_id');
        ee()->dbforge->add_key('member_id');
		ee()->dbforge->create_table('protected_links_stats', TRUE);

        return TRUE; 
        
    } 
    
    function uninstall() 
    { 
        
        ee()->load->dbforge(); 
        
        ee()->db->select('module_id'); 
        $query = ee()->db->get_where('modules', array('module_name' => 'Protected_links')); 
        
        ee()->db->where('module_id', $query->row('module_id')); 
        ee()->db->delete(version_compare(APP_VER, '6.0', '>=') ? 'module_member_roles' : 'module_member_groups'); 
        
        ee()->db->where('module_name', 'Protected_links'); 
        ee()->db->delete('modules'); 
        
        ee()->db->where('class', 'Protected_links'); 
        ee()->db->delete('actions'); 
        
        ee()->dbforge->drop_table('protected_links_files');
        ee()->dbforge->drop_table('protected_links_links');
        ee()->dbforge->drop_table('protected_links_stats');
        
        return TRUE; 
    } 
    
    function update($current='') 
    { 
        
        if (version_compare($current, '3.0.0', '<'))
        {
            $settings_q = ee()->db->select('settings')->from('modules')->where('module_name', 'Protected_links')->limit(1)->get(); 
            
            $data = array('settings' => base64_encode($settings_q->row('settings'))); 
            
            ee()->db->where('module_name', 'Protected_links'); 
            ee()->db->update('modules', $data);
        } 
        
        return TRUE; 
    } 
	

}
