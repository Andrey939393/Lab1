<?php


//SETTINGS SETTINGS SETTINGS

define('WIDTH',200);				// Thumbmail image width
define('HEIGHT',200);				// Thumbnail image height
define('EXT','.tmb');				// Thumbnail extension
define('IMAGES_PER_PAGE',36);			// Image count per one page
define('IMAGES_PER_ROW',6);			// Image count per one row

//RGB for thumbinal background
define('R',255);
define('G',255);
define('B',255);

//SETTINGS SETTINGS SETTINGS

  // This stuff we make, for normal include()  --Graf
  $parentdir=getcwd();
  chdir(dirname(__FILE__));
  $popravka=".".str_replace($parentdir,"",getcwd())."/";
  
  
  $snbreak = Explode('/', $_SERVER["SCRIPT_NAME"]);
  $scriptname = $snbreak[count($snbreak) - 1]; 

  parse_str($_SERVER['QUERY_STRING']);
  


if ( $act=='thumb' )
{
  // Intialize frequently used request parameters
  $file = $_REQUEST['file'];
  $thumb = $_REQUEST['file'].EXT;

  //Check if a thumbnail with a newer timestamp exists.
  //If exists, spool the thumbnail

  if ( (file_exists($thumb)) && (filemtime($thumb)>=filemtime($file)) )
  {
    header('Content-type: image/jpeg');
    $size = filesize($_REQUEST['file'].EXT);
    $fd = fopen($_REQUEST['file'].EXT,'rb');
    echo fread( $fd, $size );
    fclose( $fd );
    exit();
  }

  // Thumbnail doesn't exist
  // a. Make sure that file exists and that it's of supported format
  // b. Create and spool the thumbnail
  // c. Save created thumbnail 

  $imageInfo = getimagesize( $file );
  switch( $imageInfo[2] )
  {
    case 1: $image = imagecreatefromgif( $file ); break;
    case 2: $image = imagecreatefromjpeg( $file ); break;
    case 3: $image = imagecreatefrompng( $file ); break;
    default: exit();
  }

  // Create the thumnail image
  $result = imagecreatetruecolor(WIDTH,HEIGHT);
  $bg = imagecolorallocate( $result, R, G, B );
  imagefilledrectangle( $result, 0, 0, WIDTH-1, HEIGHT-1, $bg );

  // Calculate the aspect ratio for the thumbnail
  $ratio1 = $imageInfo[0]/WIDTH;
  $ratio2 = $imageInfo[1]/HEIGHT;
  $ratio = $ratio1>$ratio2?$ratio1:$ratio2;
  if ( $ratio<1 )
          $ratio=1;
  $width = $imageInfo[0]/$ratio;
  $height = $imageInfo[1]/$ratio;
  imagecopyresampled( $result, $image, (WIDTH-$width)/2, (HEIGHT-$height)/2, 0, 0, $width, $height, $imageInfo[0], $imageInfo[1]);

  // Spool thumbnail image
  header('Content-type: image/jpeg');
  imagejpeg($result);

  // Save the thumbnail image so that if requested again,thumbnail will not have to be generated
  imagejpeg($result,$thumb);

  // End program here
  exit();
}


// Get supported image types.
// Read-only GIF support somehow is not returned, see if corresponding function exists

$supported = imagetypes();
if ( function_exists('imagecreatefromgif') ) {$supported |= IMG_GIF;}

$files = array();
$thumbs = array();

$dir = opendir('.');
while( ($file=readdir( $dir ))!==false )
{
  // Skip if not a file
  if ( !is_file($file) ) continue;
		
  $ext = strrchr($file, '.');
  $extLower = strtolower($ext);
  if (
    $extLower=='.gif' && ($supported & IMG_GIF)
    || $extLower=='.png' && ($supported & IMG_PNG)
    || $extLower=='.jpeg' && ($supported & IMG_JPG)
    || $extLower=='.jpg' && ($supported & IMG_JPG)
  )
    {$files[] = $file;}
  else if ( $ext==EXT )
    {$thumbs[] = $file;}
}
closedir($dir);

if (($act=='list')or(!$act))
{
  echo "<div class='gallery_content'>";

  $ipp=IMAGES_PER_PAGE;
  if ($skip<1) {$from=1;} else {$from=$skip;}
  $to=$from+$ipp-1;
  if ($to>count($files)) {$to=count($files)-1;}
  $j=0;

  for ($i=$from;$i<=$to;$i++)
    {
    $file=$files[$i];

    if ($j==IMAGES_PER_ROW) {echo "<br/>"; $j=1;}
    else {$j++;}

    $title = $file;
    


    echo "<a href='?act=view&pic=$file'><img src='".$popravka.$scriptname."?act=thumb&file=$file' alt='$title'></a>";
    }
?>
<p>&nbsp;</p>
Размер изображения не должен превышать 1024 Кб, по ширине и высоте не более 1600 пикселей. 
<form name="upload" action="download_img.php" method="POST" ENCTYPE="multipart/form-data"> 
Выберите файл для загрузки: 
<input type="file" name="userfile">
<input type="submit" name="upload" value="Загрузить"> 
<input type="button" value="Назад" onclick="location='http://cp/Galleries/galleries.php' "/>
</form>

<?php



  // Here, we trying to display navigation  --Graf
  echo "</div><div class='gallery_navigation'>";

  if ($from>1) {echo '<a href="?act=list&skip=',$from-$ipp,'">&larr; Prev</a>';}

  $k=1;
  for ($j=1; $j <= count($files); $j=$j+$ipp) 
    {
    //if ($j==$from) {echo '<a class="gallery_button_current">',$k,"</a>";}
      //echo "<a href='?act=list&skip=$j'>$k</a>";}
   // $k++;
    }

  if ($to+$ipp<count($files)+2) {echo "<a href='?act=list&skip=",$to+1,"'>Next &rarr;</a>";}

  echo "<!-- Showing from $from to $to of ",count($files)," images. -->";
  echo "<!-- aerwe6yf464645d536c -->";

  echo "</div>";

  // Remove thumbs not associated with image files
  $extLength = strlen( EXT );
  foreach( $thumbs as $thumb )
  {
    $file = substr( $thumb, 0, strlen($thumb)-$extLength );
    if ( !file_exists($file) ) {unlink($thumb);}
  }
  
}

if ($act=='view') 

{
  echo "<div class='gallery_content'>";

  echo "<img src='".$popravka.$pic."'>";

  echo "</div><div class='gallery_navigation'>";
  

  $no=array_search($pic,$files);
  $back = $_SERVER["HTTP_REFERER"];
  //echo "<a href='?act=list&skip=".$files."'> Выход </a>"; 
 // echo "<a href='?act=view&pic=".$files[$no-1]."'> Назад </a>";
 // echo "<a href='?act=view&pic=".$files[$no+1]."'>Далее</a> ";
   //echo "<a href='?act=$z=$no'>удалить</a> ";
   chdir($parentdir);
  $id = $no; // ID статьи
  $data = parse_ini_file("likes.ini"); // Џарсим INI-файл
  $likes = $data[$id]; // Џолучаем количество лайков у статьи
    ?>
<p>&nbsp;</p>	
<div id="like" data-id="<?=$id?>"><?=$likes?></div>
 <form method = "post">
<input type="button" value="Закрыть" onclick="location='<?php echo " ?act=list&skip=".$files."";?>' "/>
<input type = "submit" name = "button2"  value = "Удалить" onclick="alert('Изображение удалено')"/>
<input type="button" value="Назад" onclick="location='<?php echo " ?act=view&pic=".$files[$no-1].""; ?>' "/>
<input type="button" value="Далее" onclick="location='<?php echo " ?act=view&pic=".$files[$no+1].""; ?>' "/>
</form>

<script>
function roll(id){
var oDiv=document.getElementById("pd_"+id);
var oPP=document.getElementById("pp_"+id);
if (oDiv.style.display=="none"){oDiv.style.display="";oPP.innerText='Скрыть форму'}
else{oDiv.style.display="none";oPP.innerText='Оставить комментарий'}}

</script>
<script src="/jquery.js" type="text/javascript"></script>
<div onclick="roll('EK')" style="margin: 20px 0px 0px">
<span id="pp_EK" style="cursor:pointer;font-weight:bolder">Оставить комментарий</span>
</div>
<div id="pd_EK" style="display:none"><div id="commentFormContent"></div></div><br>
<div id="comments_div"></div>
<script src="/cars_comments.php?script" type="text/javascript"></script>

<?
if($_POST['button2']){unlink($files[$no]); header ('Location:cars.php'); exit();  }
/* <div id="<?=$id?>""></div> */
?>





<script type="text/javascript">
$a=0;
  $(document).ready(function() {
	
	  
    $("#like").bind("click", function(event) {
      $.ajax({
        url: "like.php",
        type: "POST",
        data: ("id=" + $("#like").attr("data-id")),
        dataType: "text",
        success: function(result) {		
		
          if (result) {
				if ($a==0) {$("#like").text(Number($("#like").text()) +1); $a++;}
		  else {$("#like").text(Number($("#like").text()) -1);$a=0;
		  
		       
	
	  
    
      $.ajax({
        url: "unlike.php",
        type: "POST",
        data: ("id=" + $("#like").attr("data-id")),
        dataType: "text",
        success: function(result) {					   
        }
      });   
		  }}					  
          else alert("Error");
        }
		
      });
    });
  }); 
</script>

<?php
file_put_contents('id.txt', $id);
}
?>



