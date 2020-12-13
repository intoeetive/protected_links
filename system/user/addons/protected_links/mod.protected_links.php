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


class Protected_links {

    var $return_data	= ''; 	
    
    var $settings = array();

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	ee()->lang->loadfile('protected_links');
        
        $settings_q = ee()->db->select('settings')->from('modules')->where('module_name', 'Protected_links')->limit(1)->get(); 
        $this->settings = unserialize(base64_decode($settings_q->row('settings')));
    }
    /* END */
    
    //process the links
    function process()
    { 
        $key = explode(".", ee()->input->get('key'));
		
		$link_q = ee()->db->select("protected_links_links.*, protected_links_files.storage, protected_links_files.container, protected_links_files.endpoint, protected_links_files.url")
            ->from("protected_links_links")
            ->join("protected_links_files", "protected_links_links.file_id=protected_links_files.file_id", "left")
            ->where("accesskey", $key[0])
            ->get();
        if ($link_q->num_rows()==0)
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('invalid_request')));
        }
        
        $inline = ($link_q->row('inline')=='y') ? true : false;
        
        //hotlink?
        if ($link_q->row('deny_hotlink')=='y' && isset($_SERVER['HTTP_REFERER']))
        {
            $site_url_a = explode("/", str_replace('https://www.', '', str_replace('http://www.', '', ee()->config->item('site_url'))));
            $site_url = $site_url_a[0];
            if (strpos($_SERVER['HTTP_REFERER'], $site_url)===false)
            {
                return ee()->output->show_user_error('general', array(ee()->lang->line('hotlinking_not_allowed')));
            }
        }
        
        //admins don't have to pass checks
        if (ee()->session->userdata('group_id')==1)
        {
            $this->_serve_file($link_q->row('link_id'), $link_q->row('storage'), $link_q->row('container'), $link_q->row('endpoint'), $link_q->row('url'), $link_q->row('filename'),  $link_q->row('type'),  $link_q->row('file_id'), $inline);
            return true;
        }
        
        //no guest access?
        if (ee()->session->userdata('member_id')==0 && $link_q->row('guest_access')=='n')
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('must_log_in')));
        }
        
        //ip lock?
        if ($link_q->row('bind_ip')!='' && $link_q->row('bind_ip')!=ee()->input->ip_address && $link_q->row('guest_access')=='n')
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('wrong_ip')));
        }
        
        //expired?
        if ($link_q->row('expires')!='' && ee()->localize->now > $link_q->row('expires'))
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('link_expired')));
        }
        
        //member access check
        //if guess access is Yes than we don't care
        if ($link_q->row('member_access')!=0 && $link_q->row('guest_access')=='n')
        {
            $allowed_members = explode("|", $link_q->row('member_access'));
            $allowed_members = array_filter($allowed_members, 'strlen');
            if (!empty($allowed_members) && !in_array(ee()->session->userdata('member_id'), $allowed_members))
            {
                return ee()->output->show_user_error('general', array(ee()->lang->line('no_access')));
            }
        }
        
        //group access check
        if ($link_q->row('group_access')!='')
        {
            $allowed_groups = explode("|", $link_q->row('group_access'));
            $allowed_groups = array_filter($allowed_groups, 'strlen');
            if (version_compare(APP_VER, '6.0', '>=')) {
                $member = ee()->session->getMember();
                $role_ids = $member ? $member->getAllRoles()->pluck('role_id') : [];
            } else {
                $role_ids = [ee()->session->userdata('group_id')];
            }
            if (!empty($allowed_groups) && !array_intersect($role_ids, $allowed_groups))
            {
                return ee()->output->show_user_error('general', array(ee()->lang->line('group_no_access')));
            }
        }
        
        //max downloads reached?
        if ($link_q->row('member_limit')!='' && $link_q->row('member_limit')!=0)
        {
            if (ee()->session->userdata('member_id')==0)
            {
                $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$link_q->row('link_id')."' AND ip='".ee()->input->ip_address."'");
            }
            else
            {
                $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$link_q->row('link_id')."' AND member_id='".ee()->session->userdata('member_id')."'");
            }
            if ($check->row('cnt') >= $link_q->row('member_limit'))
            {
                return ee()->output->show_user_error('general', array(ee()->lang->line('max_downloads_reached')));
            }
        }
        
        //profile rules check
        if ($link_q->row('custom_access_rules')!='')
        {
            $custom_access_rules = unserialize($link_q->row('custom_access_rules'));
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
                return ee()->output->show_user_error('general', array(ee()->lang->line('no_access')));
            }
        }
        
        $this->_serve_file($link_q->row('link_id'), $link_q->row('storage'), $link_q->row('container'), $link_q->row('endpoint'), $link_q->row('url'), $link_q->row('filename'),  $link_q->row('type'), $link_q->row('file_id'), $inline);
        return true;
    }
    
    
    //serve the file
    function _serve_file($link_id, $storage, $container, $endpoint, $url, $filename,  $type, $file_id, $inline = false)
    {
		//echo $link_id.", <br />".$storage.", <br />".$container.", <br />".$endpoint.", <br />".$url.", <br />".$filename.",  <br />".$type.", <br />".$file_id.", <br />".$inline;
        if (ee()->session->userdata('group_id')!=1)
        {
            ee()->db->query("INSERT INTO exp_protected_links_stats (link_id, file_id, member_id, ip, dl_date) VALUES ('$link_id', '$file_id', '".ee()->session->userdata('member_id')."', '".ee()->input->ip_address."', '".ee()->localize->now."')");
            ee()->db->query("UPDATE exp_protected_links_files SET dl_count=dl_count+1, dl_date='".ee()->localize->now."' WHERE file_id=$file_id");
            ee()->db->query("UPDATE exp_protected_links_links SET dl_count=dl_count+1 WHERE link_id=$link_id");
        }
        
        $filename = (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) ? preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1) : $filename; 
        
        switch ($storage)
        {
            case 'local':
                $url = urldecode($url);
				if (!file_exists($url))
                {
                    return ee()->output->show_user_error('general', array(ee()->lang->line('file_not_exist')));
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
                $size = -1;
				if (!function_exists('curl_init'))
                {
                    $use_curl = false;
					//return ee()->output->show_user_error('general', array(ee()->lang->line('curl_required')));
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
	                //curl_setopt($curl, CURLOPT_SSLVERSION, 3);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
	        		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107 Firefox/1.0');
	        		$out = curl_exec($curl);
	        		$error = curl_error($curl);
	        		if ($error!='')
	                {
	                    return ee()->output->show_user_error('general', array(ee()->lang->line('curl_error').$error));
	                }
	        		$size = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                    if ($size<=0)
                    {
                        $size = $this->_curl_get_file_size(str_replace('&#47;','/',$url));
                    }
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
                if ($size > 0)
                {
                    header('Content-Length: ' . $size);
                }
                
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
	                //curl_setopt($curl, CURLOPT_SSLVERSION, 3);
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
                $s3 = new S3($this->settings['s3_key_id'], $this->settings['s3_key_value']);
                if ($endpoint!='')
                {
                	$s3->setEndpoint($endpoint);
                }
                $headers = $s3->getObjectInfo("$container", $url, true);
                if ($headers==false)
                {
                    //try get file size from URL then
                    $fullurl = 'http://';
                    if ($endpoint!='')
                    {
                        $fullurl .= $endpoint;
                    }
                    else
                    {
                        $fullurl .= 's3.amazonaws.com';
                    }
                    $fullurl .= '/'.$container.'/'.$url;
                    $headers['size'] = $this->_curl_get_file_size($fullurl);
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
                if (isset($headers['size']))
                {
                    header('Content-Length: '.$headers['size']);
                }
        		header("Content-Transfer-Encoding: binary");
            
                $fp = fopen("php://output", "wb");
                $s3->getObject("$container", $url, $fp);
                fclose($fp);
            break;
            
            case 'cloudfront':
                require_once(PATH_THIRD."protected_links/storage_api/amazon/S3.php");  
                $s3 = new S3($this->settings['s3_key_id'], $this->settings['s3_key_value']);
                if ($endpoint!='')
                {
                	$s3->setEndpoint($endpoint);
                }

                $s3->freeSigningKey();
                $s3->setSigningKey($this->settings['cloudfront_key_pair_id'], $this->settings['cloudfront_private_key'], false);
                $distributions = $s3->listDistributions();
                //loop though distributions, find the one that matches our origin
                $origin = $container.'.s3.amazonaws.com';
                $matching_distr = '';
                foreach ($distributions as $distr_id=>$distribution)
                {
                    if ($distribution['origin']==$origin)
                    {
                        $matching_distr = $distribution['domain'];
                        break;
                    }
                }
                
                if ($matching_distr=='')
                {
                    //no matching distribution, use common S3 processing
                    return $this->_serve_file($link_id, 's3', $container, $endpoint, $url, $filename,  $type, $file_id, $inline);
                }
                
                $fullurl = 'http://'.$matching_distr.'/'.$url; 
                
                $statement = array();
                $statement[0] = array();
                $statement[0]['Resource']    =  $fullurl;
                $statement[0]['Condition'] = array(
                     "DateLessThan" => array("AWS:EpochTime"=>time()+24*60*60),
                     "IpAddress" => array("AWS:SourceIp"=>ee()->input->ip_address())
                  
                );
                $policy = array();
                $policy['Statement'] = $statement;

                $signed_url = $s3->getSignedPolicyURL($policy);

                header("Location: $signed_url"); 
        		exit();
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
 
        session_write_close();
        exit();
    }
        
    //display the link by ID        
    function display($link_id='')
    {
        if ($link_id=='') $link_id = ee()->TMPL->fetch_param('link_id');
        
        if ($link_id=='') return ee()->TMPL->no_results();
        
        $q = ee()->db->query("SELECT * FROM exp_protected_links_links WHERE link_id='$link_id'");
        if ($q->num_rows==0)
        {
            return ee()->TMPL->no_results();
        }
        
        //admins don't have to pass checks
        if (ee()->session->userdata('group_id')==1)
        {
            return $this->_show_link($q->row('accesskey'));
        }
        
        //no guest access?
        if (ee()->session->userdata('member_id')==0 && $q->row('guest_access')=='n')
        {
            return ee()->TMPL->no_results();
        }
        
        //ip lock?
        if ($q->row('bind_ip')!='' && $q->row('bind_ip')!=ee()->input->ip_address)
        {
            return ee()->TMPL->no_results();
        }
        
        //expired?
        if ($q->row('expires')!='' && ee()->localize->now > $q->row('expires'))
        {
            return ee()->TMPL->no_results();
        }
        
        //member access check
        $allowed_members = explode("|", $q->row('member_access'));
        $allowed_members = array_filter($allowed_members, 'strlen');
        if (!empty($allowed_members) && !in_array(ee()->session->userdata('member_id'), $allowed_members))
        {
            return ee()->TMPL->no_results();
        }
        
        //group access check
        $allowed_groups = explode("|", $q->row('group_access'));
        $allowed_groups = array_filter($allowed_groups, 'strlen');
        if (version_compare(APP_VER, '6.0', '>=')) {
            $member = ee()->session->getMember();
            $role_ids = $member ? $member->getAllRoles()->pluck('role_id') : [];
        } else {
            $role_ids = [ee()->session->userdata('group_id')];
        }
        if (!empty($allowed_groups) && !array_intersect($role_ids, $allowed_groups))
        {
            return ee()->TMPL->no_results();
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
                return ee()->TMPL->no_results();
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
                return ee()->TMPL->no_results();
            }
        }
        
        return $this->_show_link($q->row('accesskey'));
        
    }


    //list all files in the system (or filtered)
    function files()
    {
        switch (ee()->TMPL->fetch_param('orderby'))
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
        $sort = (ee()->TMPL->fetch_param('sort')=='asc') ? "ASC" : "DESC";
        
        ee()->db->select();
        ee()->db->from('exp_protected_links_links');
        ee()->db->order_by($orderby, $sort);
        
        $q = ee()->db->get();
        if ($q->num_rows==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $data = array();
        
        foreach ($q->result() as $obj)
        {
            //admins don't have to pass checks
            if (ee()->session->userdata('group_id')==1)
            {
                $data[] = $obj;
                continue;
            }
            
            //no guest access?
            if (ee()->session->userdata('member_id')==0 && $obj->guest_access=='n')
            {
                continue;
            }
            
            //ip lock?
            if ($obj->bind_ip!='' && $obj->bind_ip!=ee()->input->ip_address)
            {
                continue;
            }
            
            //expired?
            if ($obj->expires!='' && $obj->expires!=0 && ee()->localize->now > $obj->expires)
            {
                continue;
            }
            
            //member access check
            $allowed_members = explode("|", $obj->member_access);
            $allowed_members = array_filter($allowed_members, 'strlen');
            if (!empty($allowed_members) && !in_array(ee()->session->userdata('member_id'), $allowed_members))
            {
                continue;
            }
            
            //group access check
            $allowed_groups = explode("|", $obj->group_access);
            $allowed_groups = array_filter($allowed_groups, 'strlen');
            if (version_compare(APP_VER, '6.0', '>=')) {
                $member = ee()->session->getMember();
                $role_ids = $member ? $member->getAllRoles()->pluck('role_id') : [];
            } else {
                $role_ids = [ee()->session->userdata('group_id')];
            }
            if (!empty($allowed_groups) && !array_intersect($role_ids, $allowed_groups))
            {
                continue;
            }
            
            //max downloads reached?
            if ($obj->member_limit!='' && $obj->member_limit!=0)
            {
                if (ee()->session->userdata('member_id')==0)
                {
                    $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND ip='".ee()->input->ip_address."'");
                }
                else
                {
                    $check = ee()->db->query("SELECT COUNT(dl_id) AS cnt FROM exp_protected_links_stats WHERE link_id='".$q->row('link_id')."' AND member_id='".ee()->session->userdata('member_id')."'");
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
                    continue;
                }
                
            }
            
            $data[] = $obj;
        }
        
        if (count($data)==0) return ee()->TMPL->no_results();
        
        ee()->load->library('typography');
		ee()->typography->initialize(array(
		 				'parse_images'		=> FALSE,
		 				'smileys'			=> FALSE,
		 				'highlight_code'	=> TRUE)
		 				);
        
        $out = '';
        foreach ($data as $obj)
        {
            $tagdata = ee()->TMPL->tagdata;
            $link = $this->_show_link($obj->accesskey);
            $tagdata = ee()->TMPL->swap_var_single('filename', $obj->filename, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('title', $obj->title, $tagdata);
            
            $description = ee()->typography->parse_type($obj->description,
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
            
            
            $tagdata = ee()->TMPL->swap_var_single('description', $description, $tagdata);
            $tagdata = ee()->TMPL->swap_var_single('link', $link, $tagdata);
            $out .= $tagdata;
        }
        
        return $out;
        
    }

    
    function _show_link($key)
    {
        $act = ee()->functions->fetch_action_id('Protected_links', 'process');
        $url = ee()->config->slash_item('site_url').ee()->config->item('index_page')."?ACT=".$act."&amp;key=".$key;
        return $url;
    }
    
    
    
    function my_downloads()
    {
    	$member_id = (ee()->TMPL->fetch_param('member_id')!==false)?ee()->TMPL->fetch_param('member_id'):ee()->session->userdata('member_id');
		if ($member_id==0)
		{
    		return ee()->TMPL->no_results();
    	}
    	
    	ee()->db->select('COUNT(dl_id) AS download_times, dl_date AS download_date, accesskey, filename, title, description')
  			->from('exp_protected_links_stats')
  			->join('exp_protected_links_links', 'exp_protected_links_stats.link_id=exp_protected_links_links.link_id', 'left')
  			->where('member_id', $member_id)
  			->group_by('exp_protected_links_stats.file_id')
  			->order_by('download_date', 'desc');
		$query = ee()->db->get();
		
		if ($query->num_rows()==0)
		{
			return ee()->TMPL->no_results();
		}
		
		$variables = array();
		
		ee()->load->library('typography');
		ee()->typography->initialize(array(
		 				'parse_images'		=> FALSE,
		 				'smileys'			=> FALSE,
		 				'highlight_code'	=> TRUE)
		 				);
		
		foreach ($query->result_array() as $row)
		{
	
	        $variable_row = $row;
	        
        	$variable_row['link'] = $this->_show_link($row['accesskey']);
        	
        	$variable_row['description'] = ee()->typography->parse_type($row['description'],
													array('text_format'	=> 'none',
															 'html_format'	=> 'none',
															 'auto_links'	=> 'n',
															 'allow_img_url' => 'n'
															 ));
        	
	        $variables[] = $variable_row;
		}
		
		$output = ee()->TMPL->parse_variables(trim(ee()->TMPL->tagdata), $variables);
		
		return $output;
    }
    
    
    
    function generate()
    { 
        if (ee()->TMPL->fetch_param('url')=='')
        {
            //return ee()->output->show_user_error('general', array(ee()->lang->line('missing_url')));
            return '';
        }
        
        if (ee()->session->userdata('member_id')==0 && ee()->TMPL->fetch_param('guest_access')!='yes' && ee()->TMPL->fetch_param('guest_access')!='on')
        {
            return ee()->TMPL->no_results();
        }
        
        $data['url'] = ee()->TMPL->fetch_param('url');
        
        if (ee()->TMPL->fetch_param('filename')!='')
        {
            $data['filename'] = ee()->TMPL->fetch_param('filename');
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
        
        if (ee()->TMPL->fetch_param('title')!='')
        {
            $data['title'] = ee()->TMPL->fetch_param('title');
        }
        else
        {
            $data['title'] = $data['filename'];
        }
        
        if (ee()->TMPL->fetch_param('storage')!='')
        {
            $data['storage'] = ee()->TMPL->fetch_param('storage');
        }
        
        if (ee()->TMPL->fetch_param('container')!='' && ee()->TMPL->fetch_param('container')!='none' && ee()->TMPL->fetch_param('container')!='false')
        {
            $data['container'] = ee()->TMPL->fetch_param('container');
        }
        
        if (ee()->TMPL->fetch_param('endpoint')!='' && ee()->TMPL->fetch_param('endpoint')!='none' && ee()->TMPL->fetch_param('endpoint')!='false')
        {
            $data['endpoint'] = ee()->TMPL->fetch_param('endpoint');
        }
        
        if (ee()->TMPL->fetch_param('content-type')!='')
        {
            $data['type'] = ee()->TMPL->fetch_param('content-type');
        }
        
        if (ee()->TMPL->fetch_param('deny_hotlink')=='yes')
        {
            $data['deny_hotlink'] = 'y';
        }
        
        if (ee()->TMPL->fetch_param('inline')=='yes')
        {
            $data['inline'] = 'y';
        }
        
        if ((ee()->TMPL->fetch_param('ip_lock')=='yes' || ee()->TMPL->fetch_param('ip_lock')=='on') || ee()->session->userdata('member_id')==0)
        {
            $data['bind_ip'] = ee()->input->ip_address;
        }

        $generate = true;
        $valid_key = '';
        //check whether link with same data exists
        ee()->db->select('expires, accesskey');
        ee()->db->from('protected_links_links');
        ee()->db->join('protected_links_files', 'exp_protected_links_links.file_id=exp_protected_links_files.file_id', 'left');
        foreach ($data as $key=>$value)
        {
            ee()->db->where("$key", "$value");
        }
        //if guest access allowed then ANY link is fine!
        if (ee()->TMPL->fetch_param('guest_access')!='yes' && ee()->TMPL->fetch_param('guest_access')!='on')
        {
            if (ee()->session->userdata('member_id')!=0)
            {
                ee()->db->where('member_access', ee()->session->userdata('member_id'));
            }
            else
            {
                ee()->db->where('guest_access', 'y');
            }
        }
        else
        {
            ee()->db->where('guest_access', 'y');
        }
        
        $q = ee()->db->get();
        
        if ($q->num_rows() > 0)
        {
            //are all of them expired?
            foreach ($q->result() as $obj)
            {
                if ($obj->expires != '' && $obj->expires != 0)
                {
                    if (!isset($allexpired)) $allexpired = true;
                    if ($obj->expires > ee()->localize->now)
                    {
                        $generate = false;
                        $allexpired = false;
                        $valid_key = $obj->accesskey;
                    }
                }
            }
            if (ee()->TMPL->fetch_param('new_link')=='yes')
            {
                $allexpired = false;
            }
            //for guests, any link is fine
            if (ee()->TMPL->fetch_param('guest_access')=='yes' || ee()->TMPL->fetch_param('guest_access')=='on')
        	{
        		if (ee()->TMPL->fetch_param('expire_in')=='' || ($obj->expires != '' && $obj->expires != 0 && $obj->expires > ee()->localize->now))
                {
                    $generate = false;	
            		$valid_key = $obj->accesskey;
                }
       		}
        }
        
        if (isset($allexpired) && $allexpired == true)
        {
            return;
        }
        
        if ($generate == true)
        {
            $valid_key = $data['accesskey'] = $this->_generate_key();
            $data['link_date'] = ee()->localize->now;
            $data['member_access'] = ee()->session->userdata('member_id');
            $data['guest_access'] = (ee()->session->userdata('member_id')==0 || ee()->TMPL->fetch_param('guest_access')=='yes' || ee()->TMPL->fetch_param('guest_access')=='on')?'y':'n';
            
            if (ee()->TMPL->fetch_param('limit')!='')
            {
                $data['member_limit'] = intval(ee()->TMPL->fetch_param('limit'));
            }
            if (ee()->TMPL->fetch_param('expire_in')!='')
            {
                $data['expires'] = strtotime(ee()->TMPL->fetch_param('expire_in'), ee()->localize->now);
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
            ee()->db->select('*');
            ee()->db->from('protected_links_files');
            ee()->db->where('storage', "{$filedata['storage']}");
            ee()->db->where('container', "{$filedata['container']}");
            ee()->db->where('endpoint', "{$filedata['endpoint']}");
            ee()->db->where('url', "{$filedata['url']}");
            ee()->db->limit(1);
            $q = ee()->db->get();
            if ($q->num_rows()==0)
            {
                ee()->db->insert('protected_links_files', $filedata);
                $data['file_id'] = ee()->db->insert_id();
            }
            else
            {
                $data['file_id'] = $q->row('file_id');
            }
            
            ee()->db->insert('protected_links_links', $data);
        }

        $url = $this->_show_link($valid_key);
        
        if (ee()->TMPL->fetch_param('ssl')=='yes')
        {
            $url = str_replace('http://', 'https://', $url);
        }
        
        if (ee()->TMPL->fetch_param('only_link')=='yes')
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
        
        $q = ee()->db->query("SELECT link_id FROM exp_protected_links_links WHERE `accesskey`='".$string."'");
        if ($q->num_rows>0)
        {
            $string = $this->_generate_key();
        }
        
        // Return the string
        return $string;
    }        
    
    
    function _curl_get_file_size($url) {
        // Assume failure.
        $result = -1;
        
        $curl = curl_init( $url );
        
        // Issue a HEAD request and follow any redirects.
        curl_setopt( $curl, CURLOPT_NOBODY, true );
        curl_setopt( $curl, CURLOPT_HEADER, true );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
        //curl_setopt( $curl, CURLOPT_USERAGENT, get_user_agent_string() );
        
        $data = curl_exec( $curl );
        curl_close( $curl );
        
        if( $data ) {
        $content_length = "unknown";
        $status = "unknown";
        
        if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
          $status = (int)$matches[1];
        }
        
        if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
          $content_length = (int)$matches[1];
        }
        
        // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
        if( $status == 200 || ($status > 300 && $status <= 308) ) {
          $result = $content_length;
        }
        }
        
        return $result;
    }
    

}
/* END */
?>
