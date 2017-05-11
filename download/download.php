<?php
class Download 
{
	const CONTENT_TYPE_TXT			= 'text/plain';
	const CONTENT_TYPE_IMAGE		= 'image/gif';
	const CONTENT_TYPE_HTML			= 'text/html';

	static public function down($type, $file) {
		if(!file_exists($file)) {
			return false;
		}
		header("Content-Type:$type");
		header("Content-Disposition:attachment;filename=$file");
		header('Content-Length:'.filesize($file));
		readfile($file);
	}
}
