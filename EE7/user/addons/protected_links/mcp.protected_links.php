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

use EllisLab\ExpressionEngine\Library\CP\Table;
use EllisLab\Addons\FilePicker\FilePicker;

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'protected_links/config.php';

class Protected_links_mcp {

    var $version = PROTECTED_LINKS_ADDON_VERSION;
    
    var $settings = array();
    
    var $perpage = 50;
    
    var $menu = array();
    
    function __construct() { 
        
        ee()->lang->loadfile('protected_links');  
        
        $settings_q = ee()->db->select('settings')->from('modules')->where('module_name', 'Protected_links')->limit(1)->get(); 
        $this->settings = unserialize(base64_decode($settings_q->row('settings')));
        
        $sidebar = ee('CP/Sidebar')->make();
        $this->menu['links'] = $sidebar->addHeader(lang('links'), ee('CP/URL', 'addons/settings/protected_links/links'))->withButton(lang('new'), ee('CP/URL', 'addons/settings/protected_links/edit'));
        $this->menu['files'] = $sidebar->addHeader(lang('files'), ee('CP/URL', 'addons/settings/protected_links/files'));
        $this->menu['members'] = $sidebar->addHeader(lang('members'), ee('CP/URL', 'addons/settings/protected_links/members'));
        $this->menu['stats'] = $sidebar->addHeader(lang('stats'), ee('CP/URL', 'addons/settings/protected_links/stats'));
        $this->menu['settings'] = $sidebar->addHeader(lang('settings'), ee('CP/URL', 'addons/settings/protected_links/settings'));
        
        
        
        $list = $this->menu['links']
            ->addBasicList();
        $list->addItem(lang('create_link'), ee('CP/URL', 'addons/settings/protected_links/edit'));
        $list->addItem(lang('cleanup_expired'), ee('CP/URL', 'addons/settings/protected_links/cleanup'))
                ->asDeleteAction('modal-confirm-cleanup');
        
        ee()->view->header = array(
			'title' => lang('protected_links_module_name'),
			'form_url' => ee('CP/URL', 'addons/settings/protected_links/links'),
			'search_button_value' => lang('btn_search_files')
		);   
        
        $modal_vars = array(
        	'name'      => 'modal-confirm-cleanup',
        	'form_url'	=> ee('CP/URL')->make('addons/settings/protected_links/cleanup'),
            'checklist' => array(
        		array(
        			'kind' => lang('cleanup_expired'),
        			'desc' => lang('confirm_cleanup'),
        		)
        	),
        	'hidden'	=> array(
                'confirmed'     => 'y'
        	)
        );
            
        $modal = ee('View')->make('ee:_shared/modal_confirm_remove')->render($modal_vars);
        ee('CP/Modal')->addModal('cleanup', $modal);     
        
    } 
    
    public function index()
    {
        return $this->links();
    }
    
    public function links()
    {
        
        $this->menu['links']->isActive();
        
        $base_url = ee('CP/URL', 'addons/settings/protected_links/links');
        
        if (ee()->input->get_post('search')!='' && strlen(ee()->input->get_post('search'))<=2)
        {
            ee('CP/Alert')->makeStandard('protected_links')
                  ->asWarning()
                  ->withTitle(lang('error'))
                  ->addToBody(lang('keyword_too_short'))
                  ->now();
        }
        
        $vars = array();
        
        $vars['form_url'] = $base_url->compile();    

        $act = ee()->db->select("action_id")->from("actions")->where('class', 'Protected_links')->where('method', 'process')->get();
        
        if (ee()->input->get_post('search')!='' && strlen(ee()->input->get_post('search'))>2)
        {
            ee()->db->where('filename LIKE "%'.ee()->db->escape_str(ee()->input->get_post('search')).'%" OR title LIKE "%'.ee()->db->escape_str(ee()->input->get_post('search')).'%"');
        }
        ee()->db->where('cp_generated', 'y');
        $total = ee()->db->count_all_results('protected_links_links');
        
        $filters = ee('CP/Filter');
        $searchFilter = ee('CP/Filter')->make('search', lang('search'), array(''=>lang('show_all')));
		$filters->add($searchFilter);
        $filters->add('Perpage', $total);
        
        $filter_values = $filters->values();
        
        $vars['filters'] = $filters->render($base_url);
        
        $base_url->addQueryStringVariables($filter_values);     
        
        $page = ((int) ee()->input->get('page')) ?: 1;
		$offset = ($page - 1) * $filter_values['perpage'];
        
        $sort_col = ee()->input->get_post('sort_col') ? ee()->input->get_post('sort_col') : 'link_id';
        $sort_dir = ee()->input->get_post('sort_dir') ? ee()->input->get_post('sort_dir') : 'desc';
                
        ee()->db->select('link_id, file_id, title, filename, accesskey, link_date, dl_count');
        ee()->db->from('protected_links_links');
        if (ee()->input->get_post('search')!==false && strlen(ee()->input->get_post('search'))>2)
        {
            ee()->db->where('filename LIKE "%'.ee()->db->escape_str(ee()->input->get_post('search')).'%" OR title LIKE "%'.ee()->db->escape_str(ee()->input->get_post('search')).'%"');
        }
        ee()->db->where('cp_generated', 'y');
        ee()->db->order_by($sort_col, $sort_dir);
        ee()->db->limit($filter_values['perpage'], $offset);
        
        $query = ee()->db->get();
        
        $table = ee('CP/Table', array('sort_col'=>$sort_col, 'sort_dir'=>$sort_dir));
        
        $table->setColumns(
          array(
            'link_id',
            'title',
            'link_date',
            'dl_count',
            'manage' => array(
              'type'  => Table::COL_TOOLBAR
            )
          )
        );
        
        $table->setNoResultsText('no_records', 'generate', ee('CP/URL', 'addons/settings/protected_links/edit'));
        
        $data = array();
        $i = 0;
        foreach ($query->result_array() as $row)
        {
           $data[$i]['link_id'] = $row['link_id'];
           $data[$i]['title'] = $row['title'];
           /*$data[$i]['filename'] = $row['filename'];*/
           $data[$i]['link_date'] = ee()->localize->format_date(ee()->localize->get_date_format(), $row['link_date']); 
           $data[$i]['dl_count'] = $row['dl_count'];
           $data[$i]['manage'] = array('toolbar_items' => array(
              'stats' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('link_id' => $row['link_id'])),
                'title' => lang('view_stats'),
                'type'  => 'txt-only',
                'content'   => 'stats'
              ),
              'file' => array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/files', array('file_id' => $row['file_id'])),
                'title' => lang('file'),
                'type'  => 'txt-only',
                'content'   => 'file'
              ),
              'edit' => array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/edit/'.$row['link_id']),
                'title' => lang('edit')
              ),
              'download' => array(
                'href' => ee()->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$row['accesskey'],
                'title' => lang('download_file')
              ),
              'remove' => array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/delete'),
                'title' => lang('delete_link'),
                'class' => 'm-link',
                'rel'   => 'modal-confirm-remove-'.$row['link_id']
              ),
            ));
            
            
            $modal_vars = array(
            	'name'      => 'modal-confirm-remove-'.$row['link_id'],
            	'form_url'	=> ee('CP/URL')->make('addons/settings/protected_links/delete'),
                'checklist' => array(
            		array(
            			'kind' => lang('link'),
            			'desc' => $row['title'],
            		)
            	),
            	'hidden'	=> array(
                    'link_id'     => $row['link_id']
            	)
            );
            
            $modal = ee('View')->make('ee:_shared/modal_confirm_remove')->render($modal_vars);
            ee('CP/Modal')->addModal('remove-'.$row['link_id'], $modal);
            
            
           $i++;
        }

        $table->setData($data);

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		$vars['pagination'] = ee('CP/Pagination', (int)$total)
			->perPage($filter_values['perpage'])
			->currentPage($page)
			->render($base_url);       
            
        return array(
          'body'       => ee('View')->make('protected_links:links')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('links'),
        );
	
    }    

    
    function files()
    {
        
        $base_url = ee('CP/URL', 'addons/settings/protected_links/files');
        
        if (ee()->input->get_post('search')!='' && strlen(ee()->input->get_post('search'))<=2)
        {
            ee('CP/Alert')->makeStandard('protected_links')
                  ->asWarning()
                  ->withTitle(lang('error'))
                  ->addToBody(lang('keyword_too_short'))
                  ->now();
        }
        
        $vars = array();
        
        $vars['form_url'] = $base_url->compile();    

        $act = ee()->db->select("action_id")->from("actions")->where('class', 'Protected_links')->where('method', 'process')->get();
        
        if (ee()->input->get_post('search')!='' && strlen(ee()->input->get_post('search'))>2)
        {
            ee()->db->like('url', ee()->input->get_post('search'));
        }
        $total = ee()->db->count_all_results('protected_links_files');
        
        $filters = ee('CP/Filter');
        $searchFilter = ee('CP/Filter')->make('search', lang('search'), array(''=>lang('show_all')));
		$filters->add($searchFilter);
        $filters->add('Perpage', $total);
        
        $filter_values = $filters->values();
        
        $vars['filters'] = $filters->render($base_url);
        
        $base_url->addQueryStringVariables($filter_values);     
        
        $page = ((int) ee()->input->get('page')) ?: 1;
		$offset = ($page - 1) * $filter_values['perpage'];
        
        $sort_col = ee()->input->get_post('sort_col') ? ee()->input->get_post('sort_col') : 'file_id';
        $sort_dir = ee()->input->get_post('sort_dir') ? ee()->input->get_post('sort_dir') : 'desc';
                
        ee()->db->select();
        ee()->db->from('protected_links_files');
        if (ee()->input->get_post('search')!='' && strlen(ee()->input->get_post('search'))>2)
        {
            ee()->db->like('url', ee()->input->get_post('search'));
        }
        ee()->db->order_by($sort_col, $sort_dir);
        ee()->db->limit($filter_values['perpage'], $offset);
        
        $query = ee()->db->get();
        
        $table = ee('CP/Table', array('sort_col'=>$sort_col, 'sort_dir'=>$sort_dir));
        
        $table->setColumns(
          array(
            'file_id',
            'file',
            'dl_count',
            'dl_date',
            'manage' => array(
              'type'  => Table::COL_TOOLBAR
            )
          )
        );
        
        $table->setNoResultsText('no_records', 'generate', ee('CP/URL', 'addons/settings/protected_links/edit'));
        
        $data = array();
        $i = 0;
        foreach ($query->result_array() as $row)
        {
           $data[$i]['file_id'] = $row['file_id'];
           $url_arr = explode("/", $row['url']);
           $data[$i]['file'] = $url_arr[(count($url_arr)-1)];
           $data[$i]['dl_count'] = $row['dl_count'];
           $data[$i]['dl_date'] = ($row['dl_date']!=0)?ee()->localize->format_date(ee()->localize->get_date_format(), $row['dl_date']):"-"; 
           
           $data[$i]['manage'] = array('toolbar_items' => array(
              'stats' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('file_id' => $row['file_id'])),
                'title' => lang('view_stats'),
                'type'  => 'txt-only',
                'content'   => 'stats'
              ),
              'links' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/links', array('file_id' => $row['file_id'])),
                'title' => lang('links'),
                'type'  => 'txt-only',
                'content'   => 'links'
              ),
              'remove' => array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/delete'),
                'title' => lang('delete_file'),
                'class' => 'm-link',
                'rel'   => 'modal-confirm-remove-'.$row['file_id']
              ),
            ));
            
            
            $modal_vars = array(
            	'name'      => 'modal-confirm-remove-'.$row['file_id'],
            	'form_url'	=> ee('CP/URL')->make('addons/settings/protected_links/delete'),
                'checklist' => array(
            		array(
            			'kind' => lang('file'),
            			'desc' => $data[$i]['file'],
            		)
            	),
            	'hidden'	=> array(
                    'file_id'     => $row['file_id']
            	)
            );
            
            $modal = ee('View')->make('ee:_shared/modal_confirm_remove')->render($modal_vars);
            ee('CP/Modal')->addModal('remove-'.$row['file_id'], $modal);
            
            
           $i++;
        }

        $table->setData($data);

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		$vars['pagination'] = ee('CP/Pagination', (int)$total)
			->perPage($filter_values['perpage'])
			->currentPage($page)
			->render($base_url);       
            
        return array(
          'body'       => ee('View')->make('protected_links:files')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('files'),
        );
	
    }

    
    function stats()
    {
        $vars = array();
        
        $sort_col = ee()->input->get_post('sort_col') ? ee()->input->get_post('sort_col') : 'link_id';
        $sort_dir = ee()->input->get_post('sort_dir') ? ee()->input->get_post('sort_dir') : 'desc';
        
        $base_url = ee('CP/URL')->make("addons/settings/protected_links/stats");
        
        $links_q = ee()->db->select('link_id, title')
            ->from('protected_links_links')
            ->where('cp_generated', 'y')
            ->get();
        $links = [];
        if ($links_q->num_rows() > 0)
        {
            foreach ($links_q->result_array() as $row)
            {
                $links[$row['link_id']] = $row['title'];
            }
        }
        
        $files_q = ee()->db->select('file_id, url')
            ->from('protected_links_files')
            ->get();
        $files = [];
        if ($files_q->num_rows() > 0)
        {
            foreach ($files_q->result_array() as $row)
            {
                $url_arr = explode("/", $row['url']);
                $files[$row['file_id']] = $url_arr[(count($url_arr)-1)];
            }
        }
        
        $filters = ee('CP/Filter');
        $linkFilter = ee('CP/Filter')->make('link_id', lang('link'), $links);
        $fileFilter = ee('CP/Filter')->make('file_id', lang('files'), $files);
		$filters->add($linkFilter);
        $filters->add($fileFilter);
        $filters->add('Date')->withName('date');
        $filters->add('Username')->withName('member_id');
        
        $filter_values = $filters->values();

        if ($filter_values['link_id']!='')
        {
            ee()->db->where('exp_protected_links_stats.link_id', $filter_values['link_id']);
        }
        if ($filter_values['file_id']!='')
        {
            ee()->db->where('exp_protected_links_stats.file_id', $filter_values['file_id']);
        }
        if ($filter_values['member_id']!='')
        {
            $where = '( exp_protected_links_stats.member_id < 0 ';
            foreach ($filter_values['member_id'] as $member_id)
            {
                $where .= ' OR exp_protected_links_stats.member_id='.$member_id;
            }
            $where .= ' )';
            ee()->db->where($where);
        }
        if ($filter_values['date']!='')
        {
            ee()->db->where('exp_protected_links_stats.dl_date > ', $filter_values['date']);
        }
        $total = ee()->db->count_all_results('protected_links_stats');
        
        $filters->add('Perpage', $total);
        
        $filter_values = $filters->values();
        
        $vars['filters'] = $filters->render($base_url);
        
        $base_url->addQueryStringVariables($filter_values);     
        
        $page = ((int) ee()->input->get('page')) ?: 1;
		$offset = ($page - 1) * $filter_values['perpage']; 
        
        $act = ee()->db->select("action_id")->from("actions")->where('class', 'Protected_links')->where('method', 'process')->get();
        
        
        ee()->db->select('protected_links_links.title, protected_links_links.accesskey, protected_links_stats.*, members.screen_name');
        ee()->db->from('protected_links_stats');
        ee()->db->join('protected_links_links', 'protected_links_stats.link_id=protected_links_links.link_id', 'left');
        ee()->db->join('members', 'protected_links_stats.member_id=members.member_id', 'left');
        if ($filter_values['link_id']!='')
        {
            ee()->db->where('exp_protected_links_stats.link_id', $filter_values['link_id']);
        }
        if ($filter_values['file_id']!='')
        {
            ee()->db->where('exp_protected_links_stats.file_id', $filter_values['file_id']);
        }
        if ($filter_values['member_id']!='')
        {
            $where = '( exp_protected_links_stats.member_id < 0 ';
            foreach ($filter_values['member_id'] as $member_id)
            {
                $where .= ' OR exp_protected_links_stats.member_id='.$member_id;
            }
            $where .= ' )';
            ee()->db->where($where);
        }
        if ($filter_values['date']!='')
        {
            ee()->db->where('exp_protected_links_stats.dl_date > ', $filter_values['date']);
        }
        ee()->db->order_by($sort_col, $sort_dir);
        ee()->db->limit($filter_values['perpage'], $offset);
        
        $table = ee('CP/Table');
        
        $table->setColumns(
          array(
            'title',
            'dl_date',
            'screen_name' => array(
                'encode'   => FALSE
            ),
            'ip_address',
            'manage' => array(
              'type'  => Table::COL_TOOLBAR
            )
          )
        );
        
        $table->setNoResultsText('no_records', 'generate', ee('CP/URL', 'addons/settings/protected_links/edit'));
        
        $query = ee()->db->get();
        
        $data = array();
        $i = 0;
        foreach ($query->result_array() as $row)
        {
           $data[$i]['title'] = $row['title'];
           $data[$i]['dl_date'] = ee()->localize->format_date(ee()->localize->get_date_format(), $row['dl_date']); 
           $data[$i]['screen_name'] = ($row['member_id']==0)?lang('guest'):'<a href="'.ee('CP/URL')->make('cp/members/profile', array('id' => $row['member_id'])).'">'.$row['screen_name'].'</a>';
           $data[$i]['ip_address'] = $row['ip'];
           $data[$i]['manage'] = array('toolbar_items' => array(
              'link_stats' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('link_id' => $row['link_id'])),
                'title' => lang('link_stats'),
                'type'  => 'view'
              ),
              'file_stats' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('file_id' => $row['file_id'])),
                'title' => lang('file_stats'),
                'type'  => 'view'
              )
            )
           );
           //if ($row['member_id']!=0)
           //{
            $data[$i]['manage']['toolbar_items']['member_stats'] = array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('member_id' => $row['member_id'])),
                'title' => lang('member_stats'),
                'type'  => 'view'
              );
           //}
              
           $data[$i]['manage']['toolbar_items']['edit'] = array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/edit/'.$row['link_id']),
                'title' => lang('edit')
              );
           $data[$i]['manage']['toolbar_items']['download'] = array(
                'href' => ee()->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$row['accesskey'],
                'title' => lang('download_file')
              );
           $data[$i]['manage']['toolbar_items']['remove'] = array(
                'href' => ee('CP/URL', 'addons/settings/protected_links/deletelink/'.$row['link_id']),
                'title' => lang('delete_link'),
                'class' => 'm-link',
                'rel'   => 'modal-confirm-remove-'.$row['link_id']
              );
          
           $i++;
        }

        $table->setData($data);
        
        $vars['table'] = $table->viewData($base_url);
        
        $vars['form_url'] = $vars['table']['base_url'];

		$vars['pagination'] = ee('CP/Pagination', (int)$total)
			->perPage($filter_values['perpage'])
			->currentPage($page)
			->render($base_url);       

        return array(
          'body'       => ee('View')->make('protected_links:stats')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('view_stats'),
        );

    }    
    

    
    function members()
    {
        
        $base_url = ee('CP/URL', 'addons/settings/protected_links/links');
        
        $vars = array();
        
        $vars['form_url'] = $base_url->compile();    

        $act = ee()->db->select("action_id")->from("actions")->where('class', 'Protected_links')->where('method', 'process')->get();
        
        $filters = ee('CP/Filter');
		$filters->add('Username')->withName('member_id');

        $filter_values = $filters->values();

        ee()->db->select('member_id');
        ee()->db->from('protected_links_stats');
        if ($filter_values['member_id']!='')
        {
            ee()->db->where('member_id', $filter_values['member_id']);
        }
        ee()->db->group_by('member_id');
        $total_q = ee()->db->get();
        $total = $total_q->num_rows();
        
        $filters->add('Perpage', $total);
        $vars['filters'] = $filters->render($base_url);
        
        $filter_values = $filters->values();
        $base_url->addQueryStringVariables($filter_values);   
          
        
        $page = ((int) ee()->input->get('page')) ?: 1;
		$offset = ($page - 1) * $filter_values['perpage']; // Offset is 0 indexed 
                
        ee()->db->select('protected_links_stats.member_id, screen_name, COUNT(dl_id) AS dl_count');
        ee()->db->from('protected_links_stats');
        ee()->db->join('protected_links_links', 'protected_links_stats.link_id=protected_links_links.link_id', 'left');
        ee()->db->join('members', 'protected_links_stats.member_id=members.member_id', 'left');
        if ($filter_values['member_id']!='')
        {
            ee()->db->where('member_id', $filter_values['member_id']);
        }
        ee()->db->group_by('protected_links_stats.member_id');
        ee()->db->limit($filter_values['perpage'], $offset);
        
        $query = ee()->db->get();
        
        $table = ee('CP/Table');
        
        $table->setColumns(
          array(
            'screen_name',
            'dl_count',
            'manage' => array(
              'type'  => Table::COL_TOOLBAR
            )
          )
        );
        
        $table->setNoResultsText('no_records');
        
        $data = array();
        $i = 0;
        foreach ($query->result_array() as $row)
        {
           $data[$i]['screen_name'] = ($row['member_id']==0)?lang('guest'):$row['screen_name'];
           $data[$i]['dl_count'] = $row['dl_count'];
           $data[$i]['manage'] = array('toolbar_items' => array(
              'stats' => array(
                'href' => ee('CP/URL')->make('addons/settings/protected_links/stats', array('member_id' => $row['member_id'])),
                'title' => lang('view_stats'),
                'type'  => 'txt-only',
                'content'   => 'stats'
              )
           ));
           if ($row['member_id']!=0)
           {
           $data[$i]['manage']['toolbar_items']['view'] = array(
                'href' => ee('CP/URL')->make('members/profile', array('member_id' => $row['member_id'])),
                'title' => lang('view')
              );
           }
            
            

            
            
           $i++;
        }

        $table->setData($data);

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		$vars['pagination'] = ee('CP/Pagination', (int)$total)
			->perPage($filter_values['perpage'])
			->currentPage($page)
			->render($base_url);       
            
        return array(
          'body'       => ee('View')->make('protected_links:links')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('members'),
        );
	
    }    
    

    
    function edit()
    {
        
        $this->menu['links']->isActive();
        
        if (ee()->input->post('storage')!==FALSE)
        {           
            
            if (ee()->input->post('url')=='')
            {
                ee('CP/Alert')->makeInline('protected_links')
                  ->asWarning()
                  ->withTitle(lang('cannot_save_link'))
                  ->addToBody(lang('missing_url'))
                  ->now();
            }
            else
            {
                $filedata = [];
                $filedata['url'] = ee()->input->post('url');
                if (ee()->input->post('link_id')=='')
                {
                    $data['accesskey'] = $this->_generate_key();
                }
                $data['cp_generated'] = 'y';
                
                if (ee()->input->post('filename'))
                {
                    $data['filename'] = ee()->input->post('filename');
                }
                else
                {
                    $url_arr = explode("/", $filedata['url']);
                    $data['filename'] = $url_arr[(count($url_arr)-1)];
                }
                
                if (ee()->input->post('title')!='')
                {
                    $data['title'] = ee()->input->post('title');
                }
                else
                {
                    $data['title'] = $data['filename'];
                }
                
                $data['description'] = ee()->input->post('description');
                $filedata['storage'] = ee()->input->post('storage');
                $filedata['container'] = ee()->input->post('container');
                $filedata['endpoint'] = ee()->input->post('endpoint');
                $data['type'] = ee()->input->post('type');
                $data['guest_access'] = ee()->input->post('guest_access');
                $data['deny_hotlink'] = ee()->input->post('deny_hotlink');
                $data['inline'] = ee()->input->post('inline');
                $data['member_limit'] = (empty(ee()->input->post('member_limit'))) ? NULL : (int) ee()->input->post('member_limit');
                $data['use_backend'] = ee()->input->post('use_backend');
                dump($data);
                
                if (!empty($_POST['group_access']))
                {
                    $data['group_access'] = implode("|",$_POST['group_access']);
                }
                else
                {
                    $data['group_access'] = '';
                }
                
          
                if (ee()->input->post('expires')!='')
                {
                    $data['expires'] = $this->_string_to_timestamp(ee()->input->post('expires'));
                }
                else
                {
                    $data['expires'] = NULL;
                }
                
                if (ee()->input->post('custom_profile_field')!='')
                {
                    $custom_access = array(ee()->input->post('custom_profile_field')=>ee()->input->post('custom_profile_value'));
                    $data['custom_access_rules'] = serialize($custom_access);
                }
                else
                {
                    $data['custom_access_rules'] = '';
                }
                
                if (ee()->input->post('link_id')!='')
                {
                    ee()->db->where('file_id', ee()->input->post('file_id'));
                    ee()->db->update('protected_links_files', $filedata);
                    
                    ee()->db->where('link_id', ee()->input->post('link_id'));
                    ee()->db->update('protected_links_links', $data);
                }
                else
                {
                    //does file exist?
                    ee()->db->select('file_id');
                    ee()->db->from('protected_links_files');
                    if (isset($filedata['storage']))
                    {
                        ee()->db->where('storage', "{$filedata['storage']}");
                    }
                    if (isset($filedata['container']))
                    {
                        ee()->db->where('container', "{$filedata['container']}");
                    }
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
                    
                    $data['link_date'] = ee()->localize->now;
                    ee()->db->insert('protected_links_links', $data);
                }
                
                ee('CP/Alert')->makeStandard('protected_links')
                  ->asSuccess()
                  ->withTitle(lang('success'))
                  ->addToBody(lang('link_saved'))
                  ->defer();
                
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/protected_links/links')->compile());
                                
            }
        }
        
        
        
        
        $filetypes = array(
            'video/x-ms-asf'                =>  'asf',
            'video/x-msvideo'               =>  'avi',
            'application/octet-stream'      =>  'exe',
            'video/quicktime'               =>  'mov',
            'audio/mpeg'                    =>  'mp3',
            'video/mpeg'                    =>  'mpg',
            'video/mpeg'                    =>  'mpeg',
            'application/pdf'               =>  'pdf',
            'application/x-rar-compressed'  =>  'rar',
            'text/plain'                    =>  'txt',
            'text/html'                     =>  'html',
            'audio/wave'                    =>  'wav',
            'audio/x-ms-wma'                =>  'wma',
            'video/x-ms-wmv'                =>  'wmv',
            'application/x-zip-compressed'  =>  'zip',
            'application/force-download'    =>  lang('force_download')
        );
                    
        $storages = array(
            'url'       => lang('url'),
            'local'     => lang('local'),
            's3'        => lang('s3'),
            'cloudfront'=> lang('cloudfront'),
            'rackspace' => lang('rackspace')
        );
        
        $endpoints = array(
			''									=>	lang('us'),
			's3-us-west-1.amazonaws.com'		=>	lang('us_west_1'),
			's3-us-west-2.amazonaws.com'		=>	lang('us_west_2'),
			's3-eu-west-1.amazonaws.com'		=>	lang('eu'),
			's3-ap-southeast-1.amazonaws.com'	=>	lang('ap_southeast_1'),
			's3-ap-southeast-2.amazonaws.com'	=>	lang('ap_southeast_2'),
			's3-ap-northeast-1.amazonaws.com'	=>	lang('ap_northeast_1'),
			's3-sa-east-1.amazonaws.com'		=>	lang('sa_east_1')
		);
        
        $data = array(
            'protected_url' => '',
            'link_id'       => '',
            'file_id'       => '',
            'storage'       => 'url',
            'container'     => '',
            'endpoint'      => '',
            'url'           => '',
            'filename'      => '',
            'title'         => '',
            'description'   => '',
            'type'          => 'application/force-download',
            'guest_access'  => 'y',
            'deny_hotlink'  => 'n',
            'inline'        => 'n',
            'group_access'  => array(),
            'expires'       => '',
            'member_limit'  => '',
            'use_backend'   => 'y',
            'custom_profile_field'  => '',
            'custom_profile_value'  => ''
        );
        
        if (ee()->uri->segment(6)!==false AND is_numeric(ee()->uri->segment(6)))
        {
            $q = ee()->db->select('protected_links_files.storage, protected_links_files.container, protected_links_files.endpoint, protected_links_files.url, protected_links_links.*')
                ->from('protected_links_links')
                ->join('protected_links_files', 'protected_links_links.file_id=protected_links_files.file_id', 'left')
                ->where('link_id', ee()->uri->segment(6))
                ->get();
            $data = $q->row_array();
               
            $act = ee()->db->select("action_id")->from("actions")->where('class', 'Protected_links')->where('method', 'process')->get();
            $data['protected_url'] = ee()->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$data['accesskey'];

            $data['group_access'] = explode("|", $data['group_access']);

            if ($data['custom_access_rules']!='')
            {
                $custom_access_arr = unserialize($data['custom_access_rules']);
                foreach ($custom_access_arr as $field=>$value)
                {
                    $data['custom_profile_field'] = $field;
                    $data['custom_profile_value'] = $value;
                }
            }
            else
            {
                $data['custom_profile_field'] = $data['custom_profile_value'] = '';
            }
            
            
            if (!in_array($q->row('storage'), array('s3', 'cloudfront', 'rackspace')))
	        {
	        	ee()->javascript->output('$("input[name=container]").parent().parent().hide();');
				if ($q->row('storage')!='s3' && $q->row('storage')!='cloudfront')
				{
					ee()->javascript->output('$("select[name=endpoint]").parent().parent().hide();');
				}
	        }
            if ($q->row('storage')=='cloudfront')
			{
				ee()->javascript->output('$("select[name=type]").parent().parent().hide();');
                ee()->javascript->output('$("select[name=inline]").parent().parent().hide();');
                ee()->javascript->output('$("input[name=filename]").parent().parent().hide();');
			}
            
        }
        else
        {
            ee()->javascript->output('$("input[name=container]").parent().parent().hide();');
      		ee()->javascript->output('$("select[name=endpoint]").parent().parent().hide();');
        }
        
        if (version_compare(APP_VER, '6.0', '>=')) {
          $RolesColl = ee('Model')
              ->get('Role')
              ->fields('role_id', 'name')
              ->filter('role_id', 'NOT IN', array(1,2,3))
              ->all();
          $group_access = array();
          $choices = [];
          foreach ($RolesColl as $coll)
          {
              $group_access[$coll->role_id] = $coll->name;
          }
        } else {
          $MemberGroupsColl = ee('Model')
              ->get('MemberGroup')
              ->fields('group_id', 'group_title')
              ->filter('group_id', 'NOT IN', array(1,2,3))
              ->all();
          $group_access = array();
          $choices = [];
          foreach ($MemberGroupsColl as $coll)
          {
              $group_access[$coll->group_id] = $coll->group_title;
          }
        }
        
        $MemberFieldsColl = ee('Model')
            ->get('MemberField')
            ->fields('m_field_id', 'm_field_label')
            ->order('m_field_order', 'ASC')
            ->all();
        $custom_fields = array();
        $custom_fields[''] = '';
        foreach ($MemberFieldsColl as $coll)
        {
            $custom_fields[$coll->m_field_id] = $coll->m_field_label;
        }
        
        $date_format = ee()->session->userdata('date_format', ee()->config->item('date_format'));

		ee()->lang->loadfile('calendar');

		ee()->javascript->set_global('date.date_format', $date_format);
		ee()->javascript->set_global('lang.date.months.full', array(
			lang('cal_january'),
			lang('cal_february'),
			lang('cal_march'),
			lang('cal_april'),
			lang('cal_may'),
			lang('cal_june'),
			lang('cal_july'),
			lang('cal_august'),
			lang('cal_september'),
			lang('cal_october'),
			lang('cal_november'),
			lang('cal_december')
		));
		ee()->javascript->set_global('lang.date.months.abbreviated', array(
			lang('cal_jan'),
			lang('cal_feb'),
			lang('cal_mar'),
			lang('cal_apr'),
			lang('cal_may'),
			lang('cal_june'),
			lang('cal_july'),
			lang('cal_aug'),
			lang('cal_sept'),
			lang('cal_oct'),
			lang('cal_nov'),
			lang('cal_dec')
		));
		ee()->javascript->set_global('lang.date.days', array(
			lang('cal_su'),
			lang('cal_mo'),
			lang('cal_tu'),
			lang('cal_we'),
			lang('cal_th'),
			lang('cal_fr'),
			lang('cal_sa'),
		));
        ee()->cp->add_js_script(array(
			'file' => array('cp/date_picker'),
		));
    ee()->javascript->set_global([
      'fileManager.fileDirectory.createUrl' => ee('CP/URL')->make('files/uploads/create')->compile(),
    ]);
    ee()->load->library('file_field');
    ee()->lang->loadfile('fieldtypes');
    ee()->file_field->loadDragAndDropAssets();
        
        $fp = new FilePicker();
		$fp->inject(ee()->view);
		$picker = $fp->link('Browse', 'all', array(
				'input' => 'url',
				'hasUpload' => TRUE
			));
        
        $vars['sections'] = array(
          array(
            array(
              'title' => '',
              'fields' => array(
                'link_id' => array(
                  'type' => 'hidden',
                  'value' => $data['link_id']
                ),
                'file_id' => array(
                  'type' => 'hidden',
                  'value' => $data['file_id']
                ),
                'url' => array(
                  'type' => 'html',
                  'content' => '<a href="'.$data['protected_url'].'" target="_blank">'.$data['protected_url'].'</a>'
                )
              )
            ),
            array(
              'title' => 'storage',
              'fields' => array(
                'storage' => array(
                  'type'    => 'select',
                  'choices' => $storages,
                  'value'   => $data['storage'],
                  'required' => TRUE
                )
              )
            ),
            array(
              'title' => 'container',
              'desc'  => 's3_rs_only',
              'fields' => array(
                'container' => array(
                  'type'    => 'text',
                  'value'   => $data['container']
                )
              )
            ),
            array(
              'title' => 'endpoint',
              'fields' => array(
                'endpoint' => array(
                  'type'    => 'select',
                  'choices' => $endpoints,
                  'value'   => $data['endpoint']
                )
              )
            ),
            array(
              'title' => 'url_or_path',
              'desc' => 'url_or_path_desc',
              'fields' => array(
                'url' => array(
                  'type' => 'text',
                  'value' => $data['url'],
                  'required' => TRUE
                ),
                'picker'  => array(
                    'type'  => 'html',
                    'content'   => $picker
                )
              )
            ),
            array(
              'title' => 'filename',
              'desc' => 'save_as',
              'fields' => array(
                'filename' => array(
                  'type' => 'text',
                  'value' => $data['filename']
                )
              )
            ),
            array(
              'title' => 'title',
              'fields' => array(
                'title' => array(
                  'type' => 'text',
                  'value' => $data['title']
                )
              )
            ),
            array(
              'title' => 'description',
              'fields' => array(
                'description' => array(
                  'type' => 'textarea',
                  'value' => $data['description']
                )
              )
            ),
            array(
              'title' => 'filetype',
              'fields' => array(
                'type' => array(
                  'type'    => 'select',
                  'choices' => $filetypes,
                  'value'   => $data['type'],
                  'required' => TRUE
                )
              )
            ),            
            array(
              'title' => 'guest_access',
              'fields' => array(
                'guest_access' => array(
                  'type'    => 'yes_no',
                  'value'   => $data['guest_access']
                )
              )
            ),                                
            array(
              'title' => 'deny_hotlink',
              'fields' => array(
                'deny_hotlink' => array(
                  'type'    => 'yes_no',
                  'value'   => $data['deny_hotlink']
                )
              )
            ),      
            array(
              'title' => 'display_inline',
              'fields' => array(
                'inline' => array(
                  'type'    => 'yes_no',
                  'value'   => $data['inline']
                )
              )
            ),                                          
            array(
              'title' => 'group_access',
              'fields' => array(
                'group_access'  => array(
                  'type' => 'checkbox',
                  'choices' => $group_access,
                  'value' => $data['group_access']
                )
              )
            ),
            array(
              'title' => 'expires',
              'fields' => array(
                'expires' => array(
                  'type' => 'html',
                  'content' => '<input type="text" name="expires" value="'.ee()->localize->format_date($date_format, $data['expires']).'" rel="date-picker"	data-timestamp="'.$data['expires'].'">',
                  'value' => $data['expires']
                )
              )
            ),
            array(
              'title' => 'member_limit',
              'fields' => array(
                'member_limit' => array(
                  'type' => 'text',
                  'value' => $data['member_limit']
                )
              )
            ),
            array(
              'title' => 'use_backend',
              'fields' => array(
                'use_backend' => array(
                  'type'    => 'yes_no',
                  'value'   => $data['use_backend']
                )
              )
            ),     
            
            array(
              'title' => 'limit_access_by_custom_field',
              'fields' => array(
                'custom_profile_field' => array(
                  'type'    => 'select',
                  'choices' => $custom_fields,
                  'value'   => $data['custom_profile_field']
                )
              )
            ),
            
            array(
              'title' => 'custom_field_value_to_access',
              'fields' => array(
                'custom_profile_value' => array(
                  'type' => 'text',
                  'value' => $data['custom_profile_value']
                )
              )
            ),
          )
        );
        
        // Final view variables we need to render the form
        $vars += array(
          'base_url' => ee('CP/URL', 'addons/settings/protected_links/edit'),
          'cp_page_title' => lang('edit_link'),
          'save_btn_text' => sprintf(lang('btn_save'), lang('link')),
          'save_btn_text_working' => lang('btn_saving')
        );
        
        
        ee()->javascript->output("
			$('select[name=storage]').change(function() {
				if ($(this).val()=='s3' || $(this).val()=='cloudfront') {
					$('input[name=container]').parent().parent().show();
					$('select[name=endpoint]').parent().parent().show();
                    if ($(this).val()=='s3') {
                        $('select[name=type]').parent().parent().show();
                        $('select[name=inline]').parent().parent().show();
                        $('input[name=filename]').parent().parent().show();
                    }
                    else
                    {
                        $('select[name=type]').parent().parent().hide();
                        $('select[name=inline]').parent().parent().hide();
                        $('input[name=filename]').parent().parent().hide();
                    }
				} else {
					if ($(this).val()=='rackspace') {
						$('input[name=container]').parent().parent().show();
					} else {
						$('input[name=container]').parent().parent().hide();
					}
					$('select[name=endpoint]').parent().parent().hide();
                    $('input[name=type]').parent().parent().hide();
				}
			});
            
            
            $('.filepicker').FilePicker({
              callback: function(data, references) {
                references.modal.find('.m-close').click();
                console.log(data);
                if (EE.fileManagerCompatibilityMode) {
                  $('input[name=url]').val(data.path);
                } else {
                  $('input[name=url]').val('{file:' + data.file_id + ':url}')
                }
                $('input[name=filename]').val(data.file_name);
                $('input[name=title]').val(data.title);
                                                
              }
            });                                    
		");
        

		ee()->javascript->compile();
        
        
        return array(
          'body'       => ee('View')->make('protected_links:edit')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('generate'),
        );

	
    }
    
    
    function settings()
    {

        if (ee()->input->post('s3_key_id')!==false)
        {
            $data = [];
            foreach ($_POST as $key=>$val)
            {
                $data[ee('Security/XSS')->clean($key)] = ee('Security/XSS')->clean($val);
            }
            
            ee()->db->where('module_name', 'Protected_links');
            ee()->db->update('modules', array('settings' => base64_encode(serialize($data))));
            
            ee('CP/Alert')->makeStandard('protected_links')
                  ->asSuccess()
                  ->withTitle(lang('success'))
                  ->addToBody(lang('preferences_updated'))
                  ->now();
        }
 
    	
        $vars['sections'] = array(
          array(
            array(
              'title' => 's3_key_id',
              'fields' => array(
                's3_key_id' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['s3_key_id']
                )
              )
            ),
            array(
              'title' => 's3_key_value',
              'fields' => array(
                's3_key_value' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['s3_key_value']
                )
              )
            ),
            array(
              'title' => 'cloudfront_key_pair_id',
              'fields' => array(
                'cloudfront_key_pair_id' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['cloudfront_key_pair_id']
                )
              )
            ),
            array(
              'title' => 'cloudfront_private_key',
              'fields' => array(
                'cloudfront_private_key' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['cloudfront_private_key']
                )
              )
            ),
            array(
              'title' => 'rackspace_api_login',
              'fields' => array(
                'rackspace_api_login' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['rackspace_api_login']
                )
              )
            ),
            array(
              'title' => 'rackspace_api_password',
              'fields' => array(
                'rackspace_api_password' => array(
                  'type'    => 'text',
                  'value'   => $this->settings['rackspace_api_password']
                )
              )
            )
          )
        );
        
        $vars += array(
          'base_url' => ee('CP/URL', 'addons/settings/protected_links/settings'),
          'cp_page_title' => lang('settings'),
          'save_btn_text' => sprintf(lang('btn_save'), lang('settings')),
          'save_btn_text_working' => lang('btn_saving')
        );
        
        return array(
          'body'       => ee('View')->make('protected_links:settings')->render($vars),
          'breadcrumb' => array(
            ee('CP/URL', 'addons/settings/protected_links/links')->compile() => lang('protected_links_module_name')
          ),
          'heading'  => lang('settings'),
        );
	
    }    
    

    
    public function delete()
    {
        $success = false;      
        
        if (ee()->input->post('link_id')!==false)
        {
            $file_q = ee()->db->select('file_id')
                ->from('protected_links_links')
                ->where('link_id', ee()->input->post('link_id'))
                ->get();
                
            if ($file_q->num_rows() == 0)
            {
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asWarning()
                    ->withTitle(lang('error'))
                    ->addToBody(lang('no_link_to_delete'))
                    ->defer();
            }
            
            ee()->db->where('link_id', ee()->input->post('link_id'));
            ee()->db->delete('protected_links_stats');
			
			ee()->db->where('link_id', ee()->input->post('link_id'));
            ee()->db->delete('protected_links_links');
            if (ee()->db->affected_rows()>0)
            {
                //check for orphans
                $other_links_q = ee()->db->select('link_id')
                    ->from('protected_links_links')
                    ->where('file_id', $file_q->row('file_id'))
                    ->get();
                if ($other_links_q->num_rows()==0)
                {
                    ee()->db->where('file_id', $file_q->row('file_id'));
                    ee()->db->delete('protected_links_files');
                }
                
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asSuccess()
                    ->withTitle(lang('success'))
                    ->addToBody(lang('link_deleted'))
                    ->defer();

            }
            else
            {
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asWarning()
                    ->withTitle(lang('error'))
                    ->addToBody(lang('no_link_to_delete'))
                    ->defer();
            }
            
        }
        
        if (ee()->input->post('file_id')!==false)
        {
            ee()->db->where('file_id', ee()->input->post('file_id'));
            ee()->db->delete('protected_links_stats');
            
            ee()->db->where('file_id', ee()->input->post('file_id'));
            ee()->db->delete('protected_links_links');
            
            ee()->db->where('file_id', ee()->input->post('file_id'));
            ee()->db->delete('protected_links_files');
            if (ee()->db->affected_rows()>0)
            {
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asSuccess()
                    ->withTitle(lang('success'))
                    ->addToBody(lang('file_deleted'))
                    ->defer();

            }
            else
            {
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asWarning()
                    ->withTitle(lang('error'))
                    ->addToBody(lang('no_file_to_delete'))
                    ->defer();
            }
            
        }



        ee()->functions->redirect(ee('CP/URL', 'addons/settings/protected_links/links')->compile());
        
        
    }
    
    public function cleanup()
    { 
        
        if (ee()->input->post('confirmed')=='y')
        {
            ee()->db->where('expires != ', 0);
            ee()->db->where('expires < ', ee()->localize->now);
            $file_q = ee()->db->select('link_id, file_id')
                ->get('protected_links_links');
                
            if ($file_q->num_rows()==0)
            {
                ee('CP/Alert')->makeStandard('protected_links')
                    ->asIssue()
                    ->withTitle(lang('notice'))
                    ->addToBody(lang('no_links_to_cleanup'))
                    ->defer();
            }
            
            foreach ($file_q->result_array() as $row)
            {
            
                ee()->db->where('link_id', $row['link_id']);
                ee()->db->delete('protected_links_stats');
    			
    			ee()->db->where('link_id', $row['link_id']);
                ee()->db->delete('protected_links_links');
                if (ee()->db->affected_rows()>0)
                {
                    //check for orphans
                    $other_links_q = ee()->db->select('link_id')
                        ->from('protected_links_links')
                        ->where('file_id', $row['file_id'])
                        ->get();
                    if ($other_links_q->num_rows()==0)
                    {
                        ee()->db->where('file_id', $row['file_id']);
                        ee()->db->delete('protected_links_files');
                    }
                }
            }
            
            ee('CP/Alert')->makeStandard('protected_links')
                    ->asSuccess()
                    ->withTitle(lang('success'))
                    ->addToBody(lang('links_cleanup_success'))
                    ->defer();
            
        }

        ee()->functions->redirect(ee('CP/URL', 'addons/settings/protected_links/links')->compile());
        
        
    }
    
    
    
    private function _generate_key($length = 16, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
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
    
    
    function _file_select($field_name)
	{
		if (version_compare(APP_VER, '2.2.0', '<'))
		{
			return;
		}
        
        // Assets is installed? Special way!
        if (array_key_exists('assets', ee()->addons->get_installed()))
        {
            require_once PATH_THIRD.'assets/helper.php';
        
            $assets_helper = new Assets_helper;
            $assets_helper->include_sheet_resources();
        
            $r = "<a href=\"#\" class=\"choose_file\"\ title=\"".ee()->lang->line('select_upload_file')."\"><img src=\"".ee()->cp->cp_theme_url."images/icon-create-upload-file.png\" alt=\"".ee()->lang->line('select_upload_file')."\"></a>";
            
            $r .= "<script type=\"text/javascript\">
            
            $(document).ready(function(){
                var mySheet = new Assets.Sheet({
    
                    // optional settings (these are the default values):
                    multiSelect: false,
                    filedirs:    'all',
                    kinds:       'any',
                
                    // onSelect callback (required):
                    onSelect:    function(files) {
                        //$('#protected_links_generate_form select[name=storage]').val('url');
                        //$('#protected_links_generate_form select[name=storage] option:contains(\"url\")').prop('selected', true);
                        $('#protected_links_generate_form input[name=url]').val(files[0].url);
                    }
                });
                
                $('.choose_file').click(function(){
                    mySheet.show();
                });
            });
            
            
            </script>";
            
            return $r;
        }
		
		ee()->lang->loadfile('fieldtypes');  
	        
        ee()->load->model('file_upload_preferences_model');
		
		if (version_compare(APP_VER, '2.4.0', '<'))
		{
			$upload_directories = ee()->file_upload_preferences_model->get_upload_preferences(ee()->session->userdata('group_id'), '');
		}
		else
		{
			$upload_directories = ee()->file_upload_preferences_model->get_file_upload_preferences(ee()->session->userdata('group_id'));
		}
		
		if (count($upload_directories) == 0) return '';
		
		foreach($upload_directories as $row)
		{
			$upload_dirs[$row['id']] = $row['name'];
		}
        
        if (count($upload_dirs) == 0) return '';
        
        ee()->load->library('filemanager');
		
		if (version_compare(APP_VER, '2.4.0', '<'))
		{
	        ee()->filemanager->filebrowser('C=content_publish&M=filemanager_actions');   
		}
		else
		{
			ee()->lang->loadfile('content');
			
			// Include dependencies
			ee()->cp->add_js_script(array(
				'plugin'    => array('scrollable', 'scrollable.navigator', 'ee_filebrowser', 'ee_fileuploader', 'tmpl', 'ee_table')
			));
			
			ee()->load->helper('html');
			
			ee()->javascript->set_global(array(
				'lang' => array(
					'resize_image'		=> ee()->lang->line('resize_image'),
					'or'				=> ee()->lang->line('or'),
					'return_to_publish'	=> ee()->lang->line('return_to_publish')
				),
				'filebrowser' => array(
					'endpoint_url'		=> 'C=content_publish&M=filemanager_actions',
					'window_title'		=> lang('file_manager'),
					'next'				=> anchor(
						'#', 
						img(
							ee()->cp->cp_theme_url . 'images/pagination_next_button.gif',
							array(
								'alt' => lang('next'),
								'width' => 13,
								'height' => 13
							)
						),
						array(
							'class' => 'next'
						)
					),
					'previous'			=> anchor(
						'#', 
						img(
							ee()->cp->cp_theme_url . 'images/pagination_prev_button.gif',
							array(
								'alt' => lang('previous'),
								'width' => 13,
								'height' => 13
							)
						),
						array(
							'class' => 'previous'
						)
					)
				),
				'fileuploader' => array(
					'window_title'		=> lang('file_upload'),
					'delete_url'		=> 'C=content_files&M=delete_files'
				)
			));
		}
	  
		$r = "<a href=\"#\" class=\"choose_file\"\ title=\"".ee()->lang->line('select_upload_file')."\"><img src=\"".ee()->cp->cp_theme_url."images/icon-create-upload-file.png\" alt=\"".ee()->lang->line('select_upload_file')."\"></a>";
        
        $r .= "<script type=\"text/javascript\">
        $(document).ready(function(){
        	var e = !1;
	$.ee_filebrowser();
	$(\"input[name=url]\", \"#protected_links_generate_form\").each(function () {
		var a = $(this).closest(\"td\"),
			b = a.find(\".choose_file\"),
			e = 'all',
			f = 'all',
			e = {
				content_type: e,
				directory: f
			};
		$.ee_filebrowser.add_trigger(b, $(this).attr(\"name\"), e, c);
	});
    function c(a, d) {
		$(\"input[name=url]\").val(a.rel_path);
	}
});
</script>";
		return $r;
		
		
	}
    
    
    function _string_to_timestamp($human_string, $localized = TRUE)
    {
        if (version_compare(APP_VER, '2.6.0', '<'))
        {
            return ee()->localize->convert_human_date_to_gmt($human_string, $localized);
        }
        else
        {
            return ee()->localize->string_to_timestamp($human_string, $localized);
        }
    }


}
/* END */
?>
