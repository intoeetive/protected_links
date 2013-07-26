
<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab "> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=files'?>"><?=lang('files')?></a>  </li> 
<li class="content_tab "> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links'?>"><?=lang('links')?></a>  </li> 
<li class="content_tab current"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=members'?>"><?=lang('members')?></a>  </li>  
<li class="content_tab "> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
</ul> 
<div class="clear_left shun"></div> 


<div id="filterMenu">
	<fieldset>
		<legend><?=lang('username_email_contains')?></legend>

	<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=members');?>

		<div class="group">
            <?php 
            $data = array(
              'name'        => 'search',
              'value'       => $selected['search'],
              'size'        => '255',
              'id'          => 'search',
              'style'       => 'width:50%'
            );
            
            echo form_input($data);
            
            echo NBS.NBS.form_submit('submit', lang('search'), 'class="submit" id="search_button"');
            
            ?>
		</div>

	<?=form_close()?>
	</fieldset>
</div>


<div style="padding: 10px;">

<?php if ($total_count == 0):?>
	<div class="tableFooter">
		<p class="notice"><?=lang('no_records')?></p>
	</div>
<?php else:?>

	<?php
		$this->table->set_template($cp_table_template);
		$this->table->set_heading($table_headings);

		echo $this->table->generate($protected_files);
	?>



<span class="pagination"><?=$pagination?></span>


<?php endif; /* if $total_count > 0*/?>

</div>


