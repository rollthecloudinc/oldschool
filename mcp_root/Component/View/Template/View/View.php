<?php 
/*
* pagination controls 
*/
echo $pager; 

/*
* Create base entity URL 
*/
printf('<a href="%s">%s</a>',(string) $create,(string) $create_label);

/*
* Row data
*/
// echo '<pre>',print_r($rows),'</pre>'; 

echo $content;

?>
