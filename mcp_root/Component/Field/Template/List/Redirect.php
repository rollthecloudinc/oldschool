<?php
/*
* Back to content entries link 
*/
echo $this->ui('Common.Field.Link',array(
	'url'=>$back_link
	,'label'=>$back_label
));

/*
* Dump the redirect content 
*/
echo $TPL_REDIRECT_CONTENT;
?>