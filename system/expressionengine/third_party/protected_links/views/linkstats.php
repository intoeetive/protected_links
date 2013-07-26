
<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab "> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=files'?>"><?=lang('files')?></a>  </li> 
<li class="content_tab current"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links'?>"><?=lang('links')?></a>  </li> 
<li class="content_tab"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=members'?>"><?=lang('members')?></a>  </li>  
<li class="content_tab "> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
</ul> 
<div class="clear_left shun"></div> 

<div id="filterMenu">
	<fieldset>
		<legend><?=lang('download_time')?></legend>

	<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=linkstats'.AMP.'link_id='.$selected['link_id']);?>

		<div class="group">
            <?php $data = array(
              'name'        => 'date_from',
              'value'       => $selected['date_from'],
              'size'        => '25',
              'id'          => 'date_from',
              'style'       => 'width:120px'
            );?>
            <?=lang('dates_from').NBS.NBS.form_input($data)?>
            <?php $data = array(
              'name'        => 'date_to',
              'value'       => $selected['date_to'],
              'size'        => '25',
              'id'          => 'date_to',
              'style'       => 'width:120px'
            );?>
            <?=lang('_to').NBS.NBS.form_input($data)?>


			<?=NBS.NBS.form_submit('submit', lang('search'), 'class="submit" id="search_button"')?>
		</div>

	<?=form_close()?>
	</fieldset>
</div>

<div style="padding: 10px;">

<p><strong><?=$selected['title']?></strong></p>

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


