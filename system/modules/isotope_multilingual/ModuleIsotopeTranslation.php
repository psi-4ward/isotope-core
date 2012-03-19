<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @author     Philipp Kaiblinger <philipp.kaiblinger@kaipo.at>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class ModuleIsotopeTranslation extends BackendModule
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_isotope_translation';


	public function generate()
	{
		$this->import('BackendUser', 'User');

		if (!strlen($this->User->translation))
		{
			return '<p class="tl_gerror">' . $GLOBALS['ISO_LANG']['ERR']['noLanguageForTranslation'] . '</p>';
		}

		if ($this->Input->get('act') == 'download')
		{
			return $this->export();
		}

		return parent::generate();
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		$this->import('Session');

		if ($this->Input->post('FORM_SUBMIT') == 'tl_translation_filters')
		{
			$arrFilter['filter_translation']['isotope_translation'] = array
			(
				'module'	=> $this->Input->post('module'),
				'file'		=> $this->Input->post('file'),
			);

			$this->Session->appendData($arrFilter);

			$this->reload();
		}

		$arrSession = $this->Session->get('filter_translation');
		$arrSession = $arrSession['isotope_translation'];


		$this->Template->headline = $GLOBALS['ISO_LANG']['MSC']['translationSelect'];
		$this->Template->action = ampersand($this->Environment->request);
		$this->Template->slabel = $GLOBALS['TL_LANG']['MSC']['save'];
		$this->Template->theme = $this->getTheme();


		// get modules
		$arrModules = array();
		foreach( $this->Config->getActiveModules() as $module )
		{
			if (strpos($module, 'isotope') === false)
				continue;

			$arrModules[] = array('value'=>$module, 'label'=>$module,'default'=>($arrSession['module'] == $module ? true : false));
		}

		// get files
		$arrFiles = array();
		if(strlen($arrSession['module']))
		{
			if (!is_dir(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/' . $this->User->translation))
			{
				$this->import('Files');
				$this->Files->mkdir('system/modules/' . $arrSession['module']. '/languages/' . $this->User->translation);
			}

			$arrFileSearch = scan(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/en/');

			foreach ($arrFileSearch as $file)
			{
				if (in_array($file, array('countries.php')))
					continue;

				$arrFiles[] = array('value'=>$file, 'label'=>$file, 'default'=>($arrSession['file'] == $file ? true : false));
			}
		}


		if (is_file(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/en/' . $arrSession['file']))
		{
			$arrSource = $this->parseFile(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/en/' . $arrSession['file']);

			if ($this->Input->post('FORM_SUBMIT') == 'isotope_translation')
			{
				$strFile = "<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Isotope eCommerce Workgroup 2009-2012";

 				$objAuthors = $this->Database->execute("SELECT * FROM tl_user WHERE translation='{$this->User->translation}'");

 				while( $objAuthors->next() )
 				{
 					$strFile .= '
 * @author     ' . $objAuthors->name . ' <' . $objAuthors->email . '>';
 				}

 				$strFile .= '
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

';

				foreach( $arrSource as $key => $value )
				{
					$value = trim($this->Input->postRaw(standardize($key, true)));

					if (!strlen($value))
						continue;

					$value = str_replace(array("\r\n", "\n", "\r", '\n'), '\n', $value, $count);

					$strFile .= $key . ' = ' . ($count > 0 ? ('"' . str_replace('"', '\"', $value) . '";'."\n") : ("'" . str_replace("'", "\'", $value) . "';\n"));
				}

				$strFile .= "\n";

				$objFile = new File('system/modules/' . $arrSession['module']. '/languages/' . $this->User->translation . '/' . $arrSession['file']);
				$objFile->write($strFile);
				$objFile->close();

				$_SESSION['TL_CONFIRM'][] = $GLOBALS['ISO_LANG']['MSC']['translationSaved'];
				$this->reload();
			}

			$this->Template->edit = true;
			$this->Template->source = $arrSource;
			$this->Template->translation = $this->parseFile(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/' . $this->User->translation . '/' . $arrSession['file']);
			$this->Template->headline = sprintf($GLOBALS['ISO_LANG']['MSC']['translationEdit'], $arrSession['file'], $arrSession['module']);

			if (!is_array($this->Template->source))
			{
				$this->Template->edit = false;
				$this->Template->error = $GLOBALS['ISO_LANG']['MSC']['translationErrorSource'];
				$this->Template->headline = $this->Template->source . '<div style="white-space:pre;overflow:scroll;font-family:Courier New"><br><br>' . str_replace("\t", '    ', htmlspecialchars(file_get_contents(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/en/' . $arrSession['file']), ENT_COMPAT, 'UTF-8')) . '</div>';
			}
			elseif (!is_array($this->Template->translation))
			{
				$this->Template->edit = false;
				$this->Template->error = $GLOBALS['ISO_LANG']['MSC']['translationError'];
				$this->Template->headline = $this->Template->translation . '<div style="white-space:pre;overflow:scroll;font-family:Courier New"><br><br>' . str_replace("\t", '    ', htmlspecialchars(file_get_contents(TL_ROOT . '/system/modules/' . $arrSession['module']. '/languages/' . $this->User->translation . '/' . $arrSession['file']), ENT_COMPAT, 'UTF-8')) . '</div>';
			}
		}

		$this->Template->modules = $arrModules;
		$this->Template->moduleClass = strlen($arrSession['module']) ? ' active' : '';
		$this->Template->files = $arrFiles;
		$this->Template->fileClass = $this->Template->edit ? ' active' : '';

		$this->Template->downloadHref = $this->addToUrl('act=download');
		$this->Template->downloadTitle = 'Download language files for this module';
		$this->Template->downloadLabel = 'Download';
	}


	private function parseFile($strFile)
	{
		$return = array();

		if (!is_file($strFile))
		{
			return array();
		}

		$data = file($strFile);

		return $this->parse($data);
	}


	private function parse($data)
	{
		$arrVariables = array();

		foreach ($data as $i => $line)
		{
			// Unset comments and empty lines
			if ($i == 0 || preg_match('@^/\*| \*|\*/|//@i', $line) || !strlen(trim($line)))
			{
				continue;
			}

			// Save language variable
			if(preg_match('@\$GLOBALS(\[.*?\])*@', $line, $match))
			{
				$strKey = $match[0];
			}
			else
			{
				return 'Line ' . ++$i . ': ' . $line;
			}

			if (eval($line) === false)
			{
				return 'Line ' . ++$i . ': ' . $line;
			}

			$varValue = eval('return '.$strKey.';');

			$this->parseVar($varValue, $strKey, $arrVariables);
		}

		return $arrVariables;
	}


	private function parseVar($varValue, $strKey, &$arrVariables)
	{
		if (is_array($varValue))
		{
			foreach( $varValue as $k => $v )
			{
				$this->parseVar($v, $strKey.'['.$k.']', $arrVariables);
			}
			return;
		}

		$arrVariables[$strKey] = $varValue;
	}



	/**
	 * Paul's Simple Diff Algorithm v 0.1
	 * (C) Paul Butler 2007 <http://www.paulbutler.org/>
	 * May be used and distributed under the zlib/libpng license.
	 *
	 * This code is intended for learning purposes; it was written with short
	 * code taking priority over performance. It could be used in a practical
	 * application, but there are a few ways it could be optimized.
	 *
	 * Given two arrays, the function diff will return an array of the changes.
	 * I won't describe the format of the array, but it will be obvious
	 * if you use print_r() on the result of a diff on some test data.
	 *
	 * htmlDiff is a wrapper for the diff command, it takes two strings and
	 * returns the differences in HTML. The tags used are <ins> and <del>,
	 * which can easily be styled with CSS.
	 **/
	function diff($old, $new)
	{
		foreach($old as $oindex => $ovalue)
		{
			$nkeys = array_keys($new, $ovalue);
			foreach($nkeys as $nindex)
			{
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ? $matrix[$oindex - 1][$nindex - 1] + 1 : 1;

				if($matrix[$oindex][$nindex] > $maxlen)
				{
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}
		}

		if($maxlen == 0)
			return array(array('d'=>$old, 'i'=>$new));

		return array_merge(
			diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
	}

	function htmlDiff($old, $new)
	{
		$diff = diff(explode(' ', $old), explode(' ', $new));

		foreach($diff as $k)
		{
			if(is_array($k))
				$ret .= (!empty($k['d'])?"<del>".implode(' ',$k['d'])."</del> ":'').
					(!empty($k['i'])?"<ins>".implode(' ',$k['i'])."</ins> ":'');
			else $ret .= $k . ' ';
		}

		return $ret;
	}

	/**
	 * Export a test translation file
	 */
	public function export()
	{
		$arrSession = $this->Session->get('filter_translation');
		$arrSession = $arrSession['isotope_translation'];

		$strFolder = 'system/modules/' . $arrSession['module'] . '/languages/' . $this->User->translation;
		$strZip = 'system/html/' . $this->User->translation . '.zip';
		$arrFiles = scan(TL_ROOT . '/' . $strFolder);

		$objZip = new ZipWriter($strZip);

		foreach( $arrFiles as $file )
		{
			$objZip->addFile($strFolder . '/' . $file);
		}

		$objZip->close();

		$objFile = new File($strZip);

		// Open the "save as …" dialogue
		header('Content-Type: ' . $objFile->mime);
		header('Content-Transfer-Encoding: binary');
		header('Content-Disposition: attachment; filename="' . $objFile->basename . '"');
		header('Content-Length: ' . $objFile->filesize);
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Expires: 0');

		$resFile = fopen(TL_ROOT . '/' . $strZip, 'rb');
		fpassthru($resFile);
		fclose($resFile);

		unlink($strZip);

		// Stop script
		exit;
	}
}

