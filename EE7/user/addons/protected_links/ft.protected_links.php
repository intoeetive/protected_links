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

class Protected_links_ft extends EE_Fieldtype {
	
	var $info = array(
		'name'		=> PROTECTED_LINKS_ADDON_NAME,
		'version'	=> PROTECTED_LINKS_ADDON_VERSION
	);
	
	// --------------------------------------------------------------------
	
	/**
	 * Display Field on Publish
	 *
	 * @access	public
	 * @param	existing data
	 * @return	field html
	 *
	 */
	function display_field($data)
	{
		$links = array();
        $links[''] = '';        
        $q = ee()->db->query("SELECT link_id, title FROM exp_protected_links_links WHERE use_backend='y' ORDER BY title ASC");
        foreach ($q->result() as $obj)
        {
            $links[$obj->link_id] = $obj->title;
        }
        
        $name = (isset($this->cell_name)) ? $this->cell_name : $this->field_name;

		$input = form_dropdown($name, $links, $data);
		
		return $input;
        
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Replace tag
	 *
	 * @access	public
	 * @param	field contents
	 * @return	replacement text
	 *
	 */
	function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT * FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows==0)
        {
            return;
        }

        if (ee()->session->userdata('group_id')==1 || $this->_check_access($q)==true)
        {
            $act_q = ee()->db->select('action_id')->from('actions')->where('class', 'Protected_links')->where('method', 'process')->get();
            $url = ee()->config->item('site_url')."?ACT=".$act_q->row('action_id')."&key=".$q->row('accesskey');
            $out = '<a href="'.$url.'" class="protected_link">'.$q->row('title').'</a>';
            return $out;
        }
        
        
        
        return;
	}
    
    function replace_link($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT * FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows==0)
        {
            return;
        }

        if (ee()->session->userdata('group_id')==1 || $this->_check_access($q)==true)
        {
            $act_q = ee()->db->select('action_id')->from('actions')->where('class', 'Protected_links')->where('method', 'process')->get();
            $url = ee()->config->item('site_url')."?ACT=".$act_q->row('action_id')."&key=".$q->row('accesskey');
            //$out = '<a href="'.$url.'" class="protected_link">'.$q->row('title').'</a>';
            return $url;
        }
        
        
        
        return;
	}

    //total downloads of link
    function replace_total_downloads($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT dl_count FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows>0)
        {
            return $q->row('dl_count');
        }

        return;
	}
	

    function replace_title($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT title FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows>0)
        {
            return $q->row('title');
        }

        return;
	}
	
	
	
	function replace_filename($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT filename FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows>0)
        {
            return $q->row('filename');
        }

        return;
	}
    
    

    function replace_description($data, $params = array(), $tagdata = FALSE)
	{
        if (empty($data)) return;
        
        $q = ee()->db->query("SELECT description FROM exp_protected_links_links WHERE link_id='$data'");
        if ($q->num_rows>0)
        {
            ee()->load->library('typography');
			ee()->typography->initialize(array(
			 				'parse_images'		=> FALSE,
			 				'smileys'			=> FALSE,
			 				'highlight_code'	=> TRUE)
			 				);
			
			$description = ee()->typography->parse_type($q->row('description'),
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
			
			return $description;
        }

        return;
	}
    
    
    function _check_access($q)
    {
        //no guest access?
        if (ee()->session->userdata('member_id')==0 && $q->row('guest_access')=='n')
        {
            return false;
        }
        
        //ip lock?
        if ($q->row('bind_ip')!='' && $q->row('bind_ip')!=ee()->input->ip_address && $q->row('guest_access')=='n')
        {
            return false;
        }
        
        //expired?
        if ($q->row('expires')!='' && ee()->localize->now > $q->row('expires'))
        {
            return false;
        }
        
        //member access check
        if ($q->row('member_access')!=0 && $q->row('guest_access')=='n')
        {
            $allowed_members = explode("|", $q->row('member_access'));
            if (!empty($allowed_members) && !in_array(ee()->session->userdata('member_id'), $allowed_members))
            {
                return false;
            }
        }
        
        //group access check
        if ($q->row('group_access')!='')
        {
            $allowed_groups = explode("|", $q->row('group_access'));
            if (!empty($allowed_groups) && !in_array(ee()->session->userdata('group_id'), $allowed_groups))
            {
                return false;
            }
        }
        
        //max downloads reached?
        if ($q->row('member_limit')!='' && $q->row('member_limit')!=0)
        {
            if (ee()->session->userdata('member_id')==0)
            {
                $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND ip='".ee()->input->ip_address."'");
            }
            else
            {
                $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND member_id='".ee()->session->userdata('member_id')."'");
            }
            if ($check->row('cnt') >= $q->row('member_limit'))
            {
                return false;
            }
        }
        
        //profile rules check
        if ($q->row('custom_access_rules')!='')
        {
            $custom_access_rules = unserialize($q->row('custom_access_rules'));
            $profile_check_passed = FALSE;
            $profile = ee()->db->query("SELECT * FROM exp_member_data WHERE member_id='".ee()->session->userdata('member_id')."'");
            foreach ($custom_access_rules as $field=>$value)
            {
                if ($profile->row('m_field_id_'.$field)==$value)
                {
                    $profile_check_passed = TRUE;
                }
            }
            if ($profile_check_passed == FALSE)
            {
                return false;
            }
        }
        
        return true;
    }
    
    function save($data)
	{
		return $data;
	}
    
    function save_settings($data) {
        return array();    
    }
    
    
   	// ------------------------
	// P&T MATRIX SUPPORT
	// ------------------------
	
	/**
	 * Display Matrix field
	 */
	function display_cell($data) {
		return $this->display_field($data);
    }
	
    function display_cell_settings($data)
	{
	   return array();  
    }
    
    function save_cell_settings($data) {
		return $this->save_settings($data);
	}
    
	function save_cell($data)
	{
		return $this->save($data);
	}
    
    /**
	 * Grid support
	 */
     
    public function accepts_content_type($name)
    {
        return ($name == 'channel' || $name == 'grid');
    }
    
	// --------------------------------------------------------------------
	
	/**
	 * Install Fieldtype
	 *
	 * @access	public
	 * @return	default global settings
	 *
	 */
	function install()
	{
		return array();
	}
	

}

/* End of file ft.google_maps.php */
/* Location: ./system/expressionengine/third_party/google_maps/ft.google_maps.php */