<?php 
echo ee('CP/Alert')->get('protected_links');
echo form_open($form_url);
echo $filters;
echo form_close();
$this->embed('ee:_shared/table', $table); 
echo $pagination; 


?>