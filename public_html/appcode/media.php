<?php

class MediaBlock
{
  function __construct ($media)
  {
    $this -> media = $media;
    $this-> show_author = FALSE;
    $this-> show_game = FALSE;
  }
  
  function show_photos_array($name)
  {
    if (!array_key_exists($name, $this -> media))
    {
      return;
    }
    foreach ($this -> media[$name] as $auth_photo)
    {
      echo '<div class="photo_table">';
      foreach ($auth_photo as $photo)
      {
        echo "<div class=\"photo_cell\">";

        $media = Media :: create ($photo);
        $media -> show_author = $this -> show_author;
        $media -> show_game = $this -> show_game;
        $media -> write_code();

        echo "</div>";
      }
      echo '</div>';
    }
  }
  
  function show()
  {
		if (is_array($this -> media))
		{
			echo "<h3>Фото/видео</h3>";
      $this -> show_photos_array ('good');
      $this -> show_photos_array ('all');
		}
  }
}

function show_media($media)
{
	$obj = new MediaBlock ($media);
	$obj-> show_author = TRUE;
	$obj -> show();
}



abstract class Media {

	public $uri = NULL;
	
	abstract function is_correct();
	abstract function get_embed_code();
	
	abstract function get_media_name();
	
	function write_code()
	{
		$photo_good = $this -> photo_good_flag ? ' (<b>Выбор модератора</b>) ' : '';
		$media_name = $this -> get_media_name();
		$photo_comment = $this -> photo_comment ? "<br> <i>{$this -> photo_comment}</i>" : '';
		
		echo $this -> get_embed_code();
		echo "<br>";
		echo ($this->uri ? "<a href=\"{$this -> uri}\">$media_name</a>" :$media_name );
		
		if ($this -> show_author)
		{
			echo " от {$this -> photo_author}";
		}
		
		if ($this -> show_game)
		{
			$profile_link = get_game_profile_link ($this -> game_id);
			echo " к игре <a href=\"{$profile_link}\">{$this -> gamename}</a>";
		}
		
		echo $this -> get_fix_link() . $photo_good .$photo_comment;

		if (check_my_priv(PHOTO_PRIV) || (check_my_priv(PHOTO_SELF_PRIV) && $this -> photo_author_id == get_user_id()))
		{
			echo "<br><a href=\"/edit/photo/?id={$this -> photo_id}\">Изменить</a>";
		}
	}
	
	function get_fix_link()
	{
		if (check_my_priv(PHOTO_PRIV) && $this -> photo_author_id == 0)
			{
				return " (<a href=\"/edit/problems/update-author/?author={$this -> photo_author}\">Исправить</a>)";
			}
		return "";
	}
	
	
	static function create ($photo)
	{
		$childs = array ("Internal_VimeoVideo", 'Internal_YouTubeVideo', 'Internal_Photo');
		foreach ($childs as $child_classname)
		{
			
			$child = new $child_classname();
			$child -> uri = $photo['photo_uri'];
			$child -> photo_id = $photo['photo_id'];
			$child -> photo_author = get_photo_author($photo);
			$child -> photo_author_id = $photo ['author_id'];
			$child -> photo_good_flag = $photo['photo_good_flag'];
			$child -> photo_comment = htmlspecialchars($photo['photo_comment']);
			
			$child -> gamename = $photo['gamename'];
			$child -> game_id = $photo['game_id'];
			
			if ($child -> is_correct())
			{
				return $child;
			}
			
		}
		
		echo 'cannot render media';
		die();
	}
}

abstract class Video extends Media {
		function get_media_name()
	{
		return 'Видео';
	}
}

class Internal_VimeoVideo extends Video {
	
	function is_correct()
	{
		return strpos ($this -> uri, 'vimeo.com/') !== FALSE;
	}
	
	function get_embed_code()
	{
		$prefix = "vimeo.com/";
		$position = strpos(strtolower($this -> uri), $prefix) + strlen($prefix);
		$id = substr($this -> uri, $position);
		
		return "<iframe src=\"http://player.vimeo.com/video/{$id}\"  width=\"536\" height=\"302\" frameborder=\"0\" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>";
	}
}

class Internal_YouTubeVideo extends Video {
	
	function is_correct()
	{
		return strpos ($this -> uri, 'youtube.com/') !== FALSE;
	}
	
	function get_embed_code()
	{
		$prefix = "youtube.com/watch?v=";
		$position = strpos(strtolower($this -> uri), $prefix) + strlen($prefix);
		$id = substr($this -> uri, $position);
		
		return "<iframe width=\"536\" height=\"302\" src=\"http://www.youtube.com/embed/{$id}\" frameborder=\"0\" allowfullscreen></iframe>";
	}
}

class Internal_Photo extends Media {
	function is_correct()
	{
		return TRUE;
	}
	
	function get_embed_code()
	{
    if ($this -> uri)
    {
      return "<a href=\"{$this -> uri}\"><img style=\"border:none\" src=\"/photo/preview/{$this -> photo_id}\" alt=\"/photo/preview/{$this -> photo_id}\"></a>";
    }
    else
    {
      return "<img style=\"border:none\" src=\"/photo/preview/{$this -> photo_id}\" alt=\"/photo/preview/{$this -> photo_id}\">";
    }
	}
	
	function get_media_name()
	{
		return 'Фото';
	}
}
?>