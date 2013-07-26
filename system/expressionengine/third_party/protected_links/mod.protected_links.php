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
 File: mod.protected_links.php
-----------------------------------------------------
 Purpose: Encrypt and protect download links
=====================================================
*/


if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}


class Protected_links {

    var $return_data	= ''; 	
    
    var $settings = array();

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	$this->EE =& get_instance(); 
    	$this->EE->lang->loadfile('protected_links');
        $query = $this->EE->db->query("SELECT settings FROM exp_modules WHERE module_name='Protected_links' LIMIT 1");
        $this->settings = unserialize($query->row('settings')); 
    }
    /* END */
    
    //process the links
    function process()
    { 
        $key = explode(".", $this->EE->input->get('key'));
		
		$q = $this->EE->db->query("SELECT exp_protected_links_links.*, exp_protected_links_files.storage, exp_protected_links_files.container,exp_protected_links_files.endpoint, exp_protected_links_files.url FROM exp_protected_links_links LEFT JOIN exp_protected_links_files ON exp_protected_links_links.file_id=exp_protected_links_files.file_id WHERE accesskey='".$key[0]."'");
        if ($q->num_rows==0)
        {
            return $this->EE->output->show_user_error('general', array($this->EE->lang->line('invalid_request')));
        }
        
        $inline = ($q->row('inline')=='y') ? true : false;
        
        //hotlink?
        if ($q->row('deny_hotlink')=='y' && isset($_SERVER['HTTP_REFERER']))
        {
            $site_url_a = explode("/", str_replace('https://www.', '', str_replace('http://www.', '', $this->EE->config->item('site_url'))));
            $site_url = $site_url_a[0];
            if (strpos($_SERVER['HTTP_REFERER'], $site_url)===false)
            {
                return $this->EE->output->show_user_error('general', array($this->EE->lang->line('hotlinking_not_allowed')));
            }
        }
        
        //admins don't have to pass checks
        if ($this->EE->session->userdata('group_id')==1)
        {
            $this->_serve_file($q->row('link_id'), $q->row('storage'), $q->row('container'), $q->row('endpoint'), $q->row('url'), $q->row('filename'),  $q->row('type'),  $q->row('file_id'), $inline);
            return true;
        }
        
        //no guest access?
        if ($this->EE->session->userdata('member_id')==0 && $q->row('guest_access')=='n')
        {
            return $this->EE->output->show_user_error('general', array($this->EE->lang->line('must_log_in')));
        }
        
        //ip lock?
        if ($q->row('bind_ip')!='' && $q->row('bind_ip')!=$this->EE->input->ip_address && $q->row('guest_access')=='n')
        {
            return $this->EE->output->show_user_error('general', array($this->EE->lang->line('wrong_ip')));
        }
        
        //expired?
        if ($q->row('expires')!='' && $this->EE->localize->now > $q->row('expires'))
        {
            return $this->EE->output->show_user_error('general', array($this->EE->lang->line('link_expired')));
        }
        
        //member access check
        //if guess access is Yes than we don't care
        if ($q->row('member_access')!=0 && $q->row('guest_access')=='n')
        {
            $allowed_members = explode("|", $q->row('member_access'));
            $allowed_members = array_filter($allowed_members, 'strlen');
            if (!empty($allowed_members) && !in_array($this->EE->session->userdata('member_id'), $allowed_members))
            {
                return $this->EE->output->show_user_error('general', array($this->EE->lang->line('no_access')));
            }
        }
        
        //group access check
        if ($q->row('group_access')!='')
        {
            $allowed_groups = explode("|", $q->row('group_access'));
            $allowed_groups = array_filter($allowed_groups, 'strlen');
            if (!empty($allowed_groups) && !in_array($this->EE->session->userdata('group_id'), $allowed_groups))
            {
                return $this->EE->output->show_user_error('general', array($this->EE->lang->line('group_no_access')));
            }
        }
        
        //max downloads reached?
        if ($q->row('member_limit')!='' && $q->row('member_limit')!=0)
        {
            if ($this->EE->session->userdata('member_id')==0)
            {
                $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND ip='".$this->EE->input->ip_address."'");
            }
            else
            {
                $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND member_id='".$this->EE->session->userdata('member_id')."'");
            }
            if ($check->row('cnt') >= $q->row('member_limit'))
            {
                return $this->EE->output->show_user_error('general', array($this->EE->lang->line('max_downloads_reached')));
            }
        }
        
        //profile rules check
        if ($q->row('custom_access_rules')!='')
        {
            $custom_access_rules = unserialize($q->row('custom_access_rules'));
            $profile_check_passed = FALSE;
            $profile = $this->EE->db->query("SELECT * FROM exp_member_data WHERE member_id='".$this->EE->session->userdata('member_id')."'");
            foreach ($custom_access_rules as $field=>$value)
            {
                if ($profile->row('m_field_id_'.$field)==$value)
                {
                    $profile_check_passed = TRUE;
                }
            }
            if ($profile_check_passed == FALSE)
            {
                return $this->EE->output->show_user_error('general', array($this->EE->lang->line('no_access')));
            }
        }
        
        $this->_serve_file($q->row('link_id'), $q->row('storage'), $q->row('container'), $q->row('endpoint'), $q->row('url'), $q->row('filename'),  $q->row('type'), $q->row('file_id'), $inline);
        return true;
    }
    
    
    //serve the file
    function _serve_file($link_id, $storage, $container, $endpoint, $url, $filename,  $type, $file_id, $inline = false)
    {
		//echo $link_id.", <br />".$storage.", <br />".$container.", <br />".$endpoint.", <br />".$url.", <br />".$filename.",  <br />".$type.", <br />".$file_id.", <br />".$inline;
        if ($this->EE->session->userdata('group_id')!=1)
        {
            $this->EE->db->query("INSERT INTO exp_protected_links_stats (link_id, file_id, member_id, ip, dl_date) VALUES ('$link_id', '$file_id', '".$this->EE->session->userdata('member_id')."', '".$this->EE->input->ip_address."', '".$this->EE->localize->now."')");
            $this->EE->db->query("UPDATE exp_protected_links_files SET dl_count=dl_count+1, dl_date='".$this->EE->localize->now."' WHERE file_id=$file_id");
            $this->EE->db->query("UPDATE exp_protected_links_links SET dl_count=dl_count+1 WHERE link_id=$link_id");
        }
        
        $filename = (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) ? preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1) : $filename; 
        
        switch ($storage)
        {
            case 'local':
                $url = urldecode($url);
				if (!file_exists($url))
                {
                    return $this->EE->output->show_user_error('general', array($this->EE->lang->line('file_not_exist')));
                }
                header("Pragma: public"); 
        		header("Expires: 0"); 
        		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
        		header("Cache-Control: public", FALSE); 
        		header("Content-Description: File Transfer"); 
        		header("Content-Type: " . $type); 
        		header("Accept-Ranges: bytes"); 
                if ($inline == true)
                {
                    header("Content-Disposition: inline; filename=\"" . $filename . "\";"); 
                }
                else
                {
                    header("Content-Disposition: attachment; filename=\"" . $filename . "\";"); 
                }
        		header("Content-Transfer-Encoding: binary"); 
                header('Content-Length: ' . filesize($url));
                @ob_clean();
                @flush();
                @readfile($url);
            break;
            
            case 'url':
                $use_curl = true;
				if (!function_exists('curl_init'))
                {
                    $use_curl = false;
					//return $this->EE->output->show_user_error('general', array($this->EE->lang->line('curl_required')));
                }
                else
                {
	                $curl = curl_init(); 
	        		curl_setopt($curl, CURLOPT_URL, str_replace('&#47;','/',$url));
	        		curl_setopt($curl, CURLOPT_HEADER, true);
	        		curl_setopt($curl, CURLOPT_NOBODY, true);
	        		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        		if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
			        {
			            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			        }
	                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	                curl_setopt($curl, CURLOPT_SSLVERSION, 3);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
	        		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107 Firefox/1.0');
	        		$out = curl_exec($curl);
	        		$error = curl_error($curl);
	        		if ($error!='')
	                {
	                    return $this->EE->output->show_user_error('general', array($this->EE->lang->line('curl_error').$error));
	                }
	        		$size = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	        		curl_close($curl);
	        		$memory = memory_get_usage(true);
	                if ($size > ($memory*3/4))	 
					{
						$use_curl = false;
					}       		
                }
                
                header("Pragma: public"); 
        		header("Expires: 0"); 
        		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
        		header("Cache-Control: public", FALSE); 
        		header("Content-Description: File Transfer"); 
        		header("Content-Type: " . $type); 
        		header("Accept-Ranges: bytes"); 
        		if ($inline == true)
                {
                    header("Content-Disposition: inline; filename=\"" . $filename . "\";"); 
                }
                else
                {
                    header("Content-Disposition: attachment; filename=\"" . $filename . "\";"); 
                }
        		header("Content-Transfer-Encoding: binary");
                
                if ($use_curl == true)
                {
                	$curl = curl_init(); 
	        		curl_setopt($curl, CURLOPT_URL, str_replace('&#47;','/',$url));
	        		curl_setopt($curl, CURLOPT_HEADER, false);
	        		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        		if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
			        {
			            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			        }
	                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	                curl_setopt($curl, CURLOPT_SSLVERSION, 3);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
	        		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107 Firefox/1.0');
	        		$out = curl_exec($curl);
	        		curl_close($curl);
	        		echo $out;
                }
                else
                {
					$fp = fopen(str_replace('&#47;','/',$url), "rb");
					while(!feof($fp)) 
					{
						echo fread($fp, 4096);
						flush();
					}
					fclose($fp);
                }
        		
            break;
            
            case 's3':
            case 'S3':
                require_once(PATH_THIRD."protected_links/storage_api/amazon/S3.php");  
                header("Pragma: public"); 
        		header("Expires: 0"); 
        		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
        		header("Cache-Control: public", FALSE); 
        		header("Content-Description: File Transfer"); 
        		header("Content-Type: " . $type); 
        		header("Accept-Ranges: bytes"); 
        		if ($inline == true)
                {
                    header("Content-Disposition: inline; filename=\"" . $filename . "\";"); 
                }
                else
                {
                    header("Content-Disposition: attachment; filename=\"" . $filename . "\";"); 
                }
        		header("Content-Transfer-Encoding: binary");
                $s3 = new S3($this->settings['s3_key_id'], $this->settings['s3_key_value']);
                if ($endpoint!='')
                {
                	$s3->setEndpoint($endpoint);
                }
                $fp = fopen("php://output", "wb");
                $s3->getObject("$container", $url, $fp);
                fclose($fp);
            break;
            
            case 'rackspace':
                require_once(PATH_THIRD."protected_links/storage_api/rackspace/cloudfiles.php"); 
                header("Pragma: public"); 
        		header("Expires: 0"); 
        		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
        		header("Cache-Control: public", FALSE); 
        		header("Content-Description: File Transfer"); 
        		header("Content-Type: " . $type); 
        		header("Accept-Ranges: bytes"); 
        		if ($inline == true)
                {
                    header("Content-Disposition: inline; filename=\"" . $filename . "\";"); 
                }
                else
                {
                    header("Content-Disposition: attachment; filename=\"" . $filename . "\";"); 
                }
        		header("Content-Transfer-Encoding: binary");
    			$auth = new CF_Authentication($this->settings['rackspace_api_login'], $this->settings['rackspace_api_password']);
    			$auth->authenticate();
    			$conn = new CF_Connection($auth);
    			$container= $conn->get_container("$container");
                $fp = fopen("php://output", "wb");
    			$url->stream($fp);
    			fclose($fp);
            break;
        }
 
        
        exit();
    }
        
    //display the link by ID        
    function display($link_id='')
    {
        if ($link_id=='') $link_id = $this->EE->TMPL->fetch_param('link_id');
        
        if ($link_id=='') return $this->EE->TMPL->no_results();
        
        $q = $this->EE->db->query("SELECT * FROM exp_protected_links_links WHERE link_id='$link_id'");
        if ($q->num_rows==0)
        {
            return $this->EE->TMPL->no_results();
        }
        
        //admins don't have to pass checks
        if ($this->EE->session->userdata('group_id')==1)
        {
            return $this->_show_link($q->row('accesskey'));
        }
        
        //no guest access?
        if ($this->EE->session->userdata('member_id')==0 && $q->row('guest_access')=='n')
        {
            return $this->EE->TMPL->no_results();
        }
        
        //ip lock?
        if ($q->row('bind_ip')!='' && $q->row('bind_ip')!=$this->EE->input->ip_address)
        {
            return $this->EE->TMPL->no_results();
        }
        
        //expired?
        if ($q->row('expires')!='' && $this->EE->localize->now > $q->row('expires'))
        {
            return $this->EE->TMPL->no_results();
        }
        
        //member access check
        $allowed_members = explode("|", $q->row('member_access'));
        $allowed_members = array_filter($allowed_members, 'strlen');
        if (!empty($allowed_members) && !in_array($this->EE->session->userdata('member_id'), $allowed_members))
        {
            return $this->EE->TMPL->no_results();
        }
        
        //group access check
        $allowed_groups = explode("|", $q->row('group_access'));
        $allowed_groups = array_filter($allowed_groups, 'strlen');
        if (!empty($allowed_groups) && !in_array($this->EE->session->userdata('group_id'), $allowed_groups))
        {
            return $this->EE->TMPL->no_results();
        }
        
        //max downloads reached?
        if ($q->row('member_limit')!='' && $q->row('member_limit')!=0)
        {
            if ($this->EE->session->userdata('member_id')==0)
            {
                $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND ip='".$this->EE->input->ip_address."'");
            }
            else
            {
                $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND member_id='".$this->EE->session->userdata('member_id')."'");
            }
            if ($check->row('cnt') >= $q->row('member_limit'))
            {
                return $this->EE->TMPL->no_results();
            }
        }
        
        //profile rules check
        if ($q->row('custom_access_rules')!='')
        {
            $custom_access_rules = unserialize($q->row('custom_access_rules'));
            $profile_check_passed = FALSE;
            $profile = $this->EE->db->query("SELECT * FROM exp_member_data WHERE member_id='".$this->EE->session->userdata('member_id')."'");
            foreach ($custom_access_rules as $field=>$value)
            {
                if ($profile->row('m_field_id_'.$field)==$value)
                {
                    $profile_check_passed = TRUE;
                }
            }
            if ($profile_check_passed == FALSE)
            {
                return $this->EE->TMPL->no_results();
            }
        }
        
        return $this->_show_link($q->row('accesskey'));
        
    }


    //list all files in the system (or filtered)
    function files()
    {
        switch ($this->EE->TMPL->fetch_param('orderby'))
        {
            case 'filename':
                $orderby = 'filename';
                break;
            case 'title':
                $orderby = 'title';
                break;
            case 'date':
            default:
                $orderby = 'link_date';
                break;
        }
        $sort = ($this->EE->TMPL->fetch_param('sort')=='asc') ? "ASC" : "DESC";
        
        $this->EE->db->select();
        $this->EE->db->from('exp_protected_links_links');
        $this->EE->db->order_by($orderby, $sort);
        
        $q = $this->EE->db->get();
        if ($q->num_rows==0)
        {
            return $this->EE->TMPL->no_results();
        }
        
        $data = array();
        
        foreach ($q->result() as $obj)
        {
            //admins don't have to pass checks
            if ($this->EE->session->userdata('group_id')==1)
            {
                $data[] = $obj;
                continue;
            }
            
            //no guest access?
            if ($this->EE->session->userdata('member_id')==0 && $obj->guest_access=='n')
            {
                continue;
            }
            
            //ip lock?
            if ($obj->bind_ip!='' && $obj->bind_ip!=$this->EE->input->ip_address)
            {
                continue;
            }
            
            //expired?
            if ($obj->expires!='' && $this->EE->localize->now > $obj->expires)
            {
                continue;
            }
            
            //member access check
            $allowed_members = explode("|", $obj->member_access);
            $allowed_members = array_filter($allowed_members, 'strlen');
            if (!empty($allowed_members) && !in_array($this->EE->session->userdata('member_id'), $allowed_members))
            {
                continue;
            }
            
            //group access check
            $allowed_groups = explode("|", $obj->group_access);
            $allowed_groups = array_filter($allowed_groups, 'strlen');
            if (!empty($allowed_groups) && !in_array($this->EE->session->userdata('group_id'), $allowed_groups))
            {
                continue;
            }
            
            //max downloads reached?
            if ($obj->member_limit!='' && $obj->member_limit!=0)
            {
                if ($this->EE->session->userdata('member_id')==0)
                {
                    $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND ip='".$this->EE->input->ip_address."'");
                }
                else
                {
                    $check = $this->EE->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND member_id='".$this->EE->session->userdata('member_id')."'");
                }
                if ($check->row('cnt') >= $obj->member_limit)
                {
                    continue;
                }
            }
            
            //profile rules check
            if ($obj->custom_access_rules!='')
            {
                $custom_access_rules = unserialize($obj->custom_access_rules);
                $profile_check_passed = FALSE;
                $profile = $this->EE->db->query("SELECT * FROM exp_member_data WHERE member_id='".$this->EE->session->userdata('member_id')."'");
                foreach ($custom_access_rules as $field=>$value)
                {
                    if ($profile->row('m_field_id_'.$field)==$value)
                    {
                        $profile_check_passed = TRUE;
                    }
                }

                if ($profile_check_passed == FALSE)
                {
                    continue;
                }
                
            }
            
            $data[] = $obj;
        }
        
        if (count($data)==0) return $this->EE->TMPL->no_results();
        
        $this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
		 				'parse_images'		=> FALSE,
		 				'smileys'			=> FALSE,
		 				'highlight_code'	=> TRUE)
		 				);
        
        $out = '';
        foreach ($data as $obj)
        {
            $tagdata = $this->EE->TMPL->tagdata;
            $link = $this->_show_link($obj->accesskey);
            $tagdata = $this->EE->TMPL->swap_var_single('filename', $obj->filename, $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('title', $obj->title, $tagdata);
            
            $description = $this->EE->typography->parse_type($obj->description,
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
            
            
            $tagdata = $this->EE->TMPL->swap_var_single('description', $description, $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('link', $link, $tagdata);
            $out .= $tagdata;
        }
        
        return $out;
        
    }

    
    function _show_link($key)
    {
        $act = $this->EE->functions->fetch_action_id('Protected_links', 'process');
        $url = $this->EE->config->item('site_url')."?ACT=".$act."&amp;key=".$key;
        return $url;
    }
    
    
    
    function my_downloads()
    {
    	$member_id = ($this->EE->TMPL->fetch_param('member_id')!==false)?$this->EE->TMPL->fetch_param('member_id'):$this->EE->session->userdata('member_id');
		if ($member_id==0)
		{
    		return $this->EE->TMPL->no_results();
    	}
    	
    	$this->EE->db->select('COUNT(dl_id) AS download_times, dl_date AS download_date, accesskey, filename, title, description')
  			->from('exp_protected_links_stats')
  			->join('exp_protected_links_links', 'exp_protected_links_stats.link_id=exp_protected_links_links.link_id', 'left')
  			->where('member_id', $member_id)
  			->group_by('exp_protected_links_stats.file_id')
  			->order_by('download_date', 'desc');
		$query = $this->EE->db->get();
		
		if ($query->num_rows()==0)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$variables = array();
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
		 				'parse_images'		=> FALSE,
		 				'smileys'			=> FALSE,
		 				'highlight_code'	=> TRUE)
		 				);
		
		foreach ($query->result_array() as $row)
		{
	
	        $variable_row = $row;
	        
        	$variable_row['link'] = $this->_show_link($row['accesskey']);
        	
        	$variable_row['description'] = $this->EE->typography->parse_type($row['description'],
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
        	
	        $variables[] = $variable_row;
		}
		
		$output = $this->EE->TMPL->parse_variables(trim($this->EE->TMPL->tagdata), $variables);
		
		return $output;
    }
    
    
    
    function generate()
    { 
        if ($this->EE->TMPL->fetch_param('url')=='')
        {
            //return $this->EE->output->show_user_error('general', array($this->EE->lang->line('missing_url')));
            return '';
        }
        
        if ($this->EE->session->userdata('member_id')==0 && $this->EE->TMPL->fetch_param('guest_access')!='yes' && $this->EE->TMPL->fetch_param('guest_access')!='on')
        {
            return $this->EE->TMPL->no_results();
        }
        
        $data['url'] = $this->EE->TMPL->fetch_param('url');
        
        if ($this->EE->TMPL->fetch_param('filename')!='')
        {
            $data['filename'] = $this->EE->TMPL->fetch_param('filename');
        }
        else
        {
            $url_arr = explode("/", $data['url']);
            $data['filename'] = $url_arr[(count($url_arr)-1)];
            if (strpos($data['filename'], '?')!==false)
            {
            	$arr = explode("?", $data['filename']);
            	$data['filename'] = $arr[1];
            }
        }
        
        if ($this->EE->TMPL->fetch_param('title')!='')
        {
            $data['title'] = $this->EE->TMPL->fetch_param('title');
        }
        else
        {
            $data['title'] = $data['filename'];
        }
        
        if ($this->EE->TMPL->fetch_param('storage')!='')
        {
            $data['storage'] = $this->EE->TMPL->fetch_param('storage');
        }
        
        if ($this->EE->TMPL->fetch_param('container')!='' && $this->EE->TMPL->fetch_param('container')!='none' && $this->EE->TMPL->fetch_param('container')!='false')
        {
            $data['container'] = $this->EE->TMPL->fetch_param('container');
        }
        
        if ($this->EE->TMPL->fetch_param('endpoint')!='' && $this->EE->TMPL->fetch_param('endpoint')!='none' && $this->EE->TMPL->fetch_param('endpoint')!='false')
        {
            $data['endpoint'] = $this->EE->TMPL->fetch_param('endpoint');
        }
        
        if ($this->EE->TMPL->fetch_param('content-type')!='')
        {
            $data['type'] = $this->EE->TMPL->fetch_param('content-type');
        }
        
        if ($this->EE->TMPL->fetch_param('deny_hotlink')=='yes')
        {
            $data['deny_hotlink'] = 'y';
        }
        
        if ($this->EE->TMPL->fetch_param('inline')=='yes')
        {
            $data['inline'] = 'y';
        }
        
        if (($this->EE->TMPL->fetch_param('ip_lock')=='yes' || $this->EE->TMPL->fetch_param('ip_lock')=='on') || $this->EE->session->userdata('member_id')==0)
        {
            $data['bind_ip'] = $this->EE->input->ip_address;
        }

        $generate = true;
        $valid_key = '';
        //check whether link with same data exists
        $this->EE->db->select('expires, accesskey');
        $this->EE->db->from('protected_links_links');
        $this->EE->db->join('protected_links_files', 'exp_protected_links_links.file_id=exp_protected_links_files.file_id', 'left');
        foreach ($data as $key=>$value)
        {
            $this->EE->db->where("$key", "$value");
        }
        //if guest access allowed then ANY link is fine!
        if ($this->EE->TMPL->fetch_param('guest_access')!='yes' && $this->EE->TMPL->fetch_param('guest_access')!='on')
        {
            if ($this->EE->session->userdata('member_id')!=0)
            {
                $this->EE->db->where('member_access', $this->EE->session->userdata('member_id'));
            }
            else
            {
                $this->EE->db->where('guest_access', 'y');
            }
        }
        
        $q = $this->EE->db->get();
        
        if ($q->num_rows() > 0)
        {
            //are all of them expired?
            foreach ($q->result() as $obj)
            {
                if ($obj->expires != '' && $obj->expires != 0)
                {
                    if (!isset($allexpired)) $allexpired = true;
                    if ($obj->expires > $this->EE->localize->now)
                    {
                        $generate = false;
                        $allexpired = false;
                        $valid_key = $obj->accesskey;
                    }
                }
            }
            if ($this->EE->TMPL->fetch_param('new_link')=='yes')
            {
                $allexpired = false;
            }
            //for guests, any link is fine
            if ($this->EE->TMPL->fetch_param('guest_access')=='yes' || $this->EE->TMPL->fetch_param('guest_access')=='on')
        	{
        		$generate = false;	
        		$valid_key = $obj->accesskey;
       		}
        }
        
        if (isset($allexpired) && $allexpired == true)
        {
            return;
        }
        
        if ($generate == true)
        {
            $valid_key = $data['accesskey'] = $this->_generate_key();
            $data['link_date'] = $this->EE->localize->now;
            $data['member_access'] = $this->EE->session->userdata('member_id');
            $data['guest_access'] = ($this->EE->session->userdata('member_id')==0 || $this->EE->TMPL->fetch_param('guest_access')=='yes' || $this->EE->TMPL->fetch_param('guest_access')=='on')?'y':'n';
            
            if ($this->EE->TMPL->fetch_param('limit')!='')
            {
                $data['member_limit'] = intval($this->EE->TMPL->fetch_param('limit'));
            }
            if ($this->EE->TMPL->fetch_param('expire_in')!='')
            {
                $data['expires'] = strtotime($this->EE->TMPL->fetch_param('expire_in'), $this->EE->localize->now);
            }
    
            
            if (isset($data['storage']))
            {
                $filedata['storage'] = $data['storage'];
                unset($data['storage']);
            } 
            else
            {
                $filedata['storage'] = 'url';
            }
            
            if (isset($data['container']))
            {
                $filedata['container'] = $data['container'];
                unset($data['container']);
            } 
            else
            {
                $filedata['container'] = '';
            }
            
            if (isset($data['endpoint']))
            {
                $filedata['endpoint'] = $data['endpoint'];
                unset($data['endpoint']);
            } 
            else
            {
                $filedata['endpoint'] = '';
            }
            
            if (isset($data['url']))
            {
                $filedata['url'] = $data['url'];
                unset($data['url']);
            } 
            
            //does file exist?
            $this->EE->db->select('*');
            $this->EE->db->from('protected_links_files');
            $this->EE->db->where('storage', "{$filedata['storage']}");
            $this->EE->db->where('container', "{$filedata['container']}");
            $this->EE->db->where('endpoint', "{$filedata['endpoint']}");
            $this->EE->db->where('url', "{$filedata['url']}");
            $this->EE->db->limit(1);
            $q = $this->EE->db->get();
            if ($q->num_rows()==0)
            {
                $this->EE->db->insert('protected_links_files', $filedata);
                $data['file_id'] = $this->EE->db->insert_id();
            }
            else
            {
                $data['file_id'] = $q->row('file_id');
            }
            
            $this->EE->db->insert('protected_links_links', $data);
        }

        $url = $this->_show_link($valid_key);
        
        if ($this->EE->TMPL->fetch_param('ssl')=='yes')
        {
            $url = str_replace('http://', 'https://', $url);
        }
        
        if ($this->EE->TMPL->fetch_param('only_link')=='yes')
        {
            return $url;
        }
        else
        {
            $out = '<a href="'.$url.'" class="protected_link">'.$data['title'].'</a>';
            return $out;
        }
    }
    
    
    function _generate_key($length = 16, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
    {
        // Length of character list
        $chars_length = (strlen($chars) - 1);
    
        // Start our string
        $string = $chars[rand(0, $chars_length)];
        
        // Generate random string
        for ($i = 1; $i < $length; $i++)
        {
            // Grab a random character from our list
            $r = $chars[rand(0, $chars_length)];
            
            // Make sure the same two characters don't appear next to each other
            //if ($r != $string{$i - 1}) $string .=  $r;
            $string .=  $r;
        }
        
        $q = $this->EE->db->query("SELECT link_id FROM exp_protected_links_links WHERE `accesskey`='".$string."'");
        if ($q->num_rows>0)
        {
            $string = $this->_generate_key();
        }
        
        // Return the string
        return $string;
    }        
    

}
/* END */
?>