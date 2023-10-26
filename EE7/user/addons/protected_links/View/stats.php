<?php
echo form_open($form_url);
echo $filters;
echo form_close();
$this->embed('ee:_shared/table', $table); 
?>