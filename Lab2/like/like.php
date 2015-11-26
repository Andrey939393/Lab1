<?php

  $text = file_get_contents("likes.ini"); 
  $likes = explode("\n", $text);
  $new_likes = array(); 
  for ($i = 0; $i < count($likes); $i++) {
    $parts = explode("=", $likes[$i]); 
    if ($parts[0] == $_POST["id"]) $parts[1] += 1; 
    $new_likes[] = implode("=", $parts);
  }
  $text = implode("\n", $new_likes); 
  echo file_put_contents("likes.ini", $text); 
  ?>
