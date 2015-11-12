<?php

class XenDevTools_Helper_Zip
{
	protected function recurseZip($src, &$zip, $path)
	{
		$dir = opendir($src);

		while (false !== ($file = readdir($dir)))
		{
			if (($file != '.') && ($file != '..'))
			{
				if (is_dir($src . '/' . $file))
				{
					$this->recurseZip($src . '/' . $file, $zip, $path);
				}
				else
				{
					$zip->addFile($src . '/' . $file, substr($src . '/' . $file, $path));
				}
			}
		}

		closedir($dir);
	}

	public function compress($src, $dst='', $buildDir, $addOnId)
	{
		if (substr($src, -1) === '/')
		{
			$src = substr($src, 0, -1);
		}
		if (substr($dst, -1) === '/')
		{
			$dst = substr($dst, 0, -1);
		}

		$path = strlen(dirname($src) . '/');

		$filename = $addOnId . '.zip';

		$dst = empty($dst) ? $filename : $dst . '/' . $filename;
		@unlink($dst);

		$zip = new ZipArchive;

		$res = $zip->open($dst, ZipArchive::CREATE);

		if ($res !== TRUE)
		{
			echo 'Error: Unable to create zip file';
			exit;
		}

		if (is_file($src))
		{
			$zip->addFile($src, substr($src, $path));

		}
		else
		{
			if (!is_dir($src))
			{
				$zip->close();
				@unlink($dst);
				echo 'Error: File not found';
				exit;
			}

			$this->recurseZip($src, $zip, $path);
		}

		$src = str_replace('upload', '', $src);

		$dir = opendir($src);

		$file = readdir($dir);

		while (false !== ($file = readdir($dir)))
		{
			if (strstr($file, '.xml'))
			{
				$xmlFile = $file;
			}
		}

		$zip->addFile($src . $xmlFile, $xmlFile);

		$zip->close();

		return $dst;
	}
}