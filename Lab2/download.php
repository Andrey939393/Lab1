<?php
$uploaddir = 'Z:\home\cp\www\Gallery\t';
// это папка, в которую будет загружаться картинка
$apend=date('YmdHis').rand(100,1000).'.jpg'; 
// это имя, которое будет присвоенно изображению 
$uploadfile = "$uploaddir$apend"; 
//в переменную $uploadfile будет входить папка и имя изображения

// В данной строке самое важное - проверяем загружается ли изображение (а может вредоносный код?)
// И проходит ли изображение по весу. В нашем случае до 1024 Кб

if(($_FILES['userfile']['type'] == 'image/gif' || $_FILES['userfile']['type'] == 'image/jpeg' || $_FILES['userfile']['type'] == 'image/png') && ($_FILES['userfile']['size'] != 0 and $_FILES['userfile']['size']<=1024000)) 
{ 
// Указываем максимальный вес загружаемого файла. Сейчас до 1024 Кб 
  if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) 
   { 
   //Здесь идет процесс загрузки изображения 
   $size = getimagesize($uploadfile); 
   // с помощью этой функции мы можем получить размер пикселей изображения 
     if ($size[0] < 1601 && $size[1]<1601) 
     { 
		

     // если размер изображения не более 1600 пикселей по ширине и не более 1600 по  высоте 
     echo "Файл успешно загружен."; 
	 $back = $_SERVER["HTTP_REFERER"];?>
	 <p>&nbsp;</p>
<input type="button" value="Назад" onclick="location='<?php echo $back ?>' "/>
<div class="button_style"><a href="<?php echo $back;?>" ></a></div>
<?php
     } else {
     echo "Загружаемое изображение превышает допустимые нормы (ширина не более - 1600; высота не более 1600)"; 
     unlink($uploadfile); 
	 	;?>
	 <p>&nbsp;</p>
<input type="button" value="Назад" onclick="location='<?php echo $back ?>' "/>
<div class="button_style"><a href="<?php echo $back;?>" ></a></div>
<?php
     // удаление файла 
     } 
   } else {
   echo "Файл не загружен, вернитеcь и попробуйте еще раз";
   	 $back = $_SERVER["HTTP_REFERER"];?>
	 <p>&nbsp;</p>
<input type="button" value="Назад" onclick="location='<?php echo $back ?>' "/>
<div class="button_style"><a href="<?php echo $back;?>" ></a></div>
<?php
   } 
} else { 
echo "Размер файла не соответсвует требуемому";
	 $back = $_SERVER["HTTP_REFERER"];?>
	 <p>&nbsp;</p>
<input type="button" value="Назад" onclick="location='<?php echo $back ?>' "/>
<div class="button_style"><a href="<?php echo $back;?>" ></a></div>
<?php
}
?>
