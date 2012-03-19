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
 * @copyright  Winans Creative 2009, Intelligent Spark 2010, iserv.ch GmbH 2010
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


require_once(TL_ROOT . '/system/drivers/DC_Table.php');

/**
 * Based on DC_Table from Contao 2.9.2
 */
class DC_ProductData extends DC_Table
{

	/**
	 * True if we are editing a language
	 */
	protected $blnEditLanguage;


	/**
	 * Array of languages for this product's type
	 */
	protected $arrLanguages;


	/**
	 * List all records of a particular table
	 * @return string
	 */
	public function showAll()
	{
		$return = '';
		$this->limit = '';
		$this->bid = 'tl_buttons';

		// Clean up old tl_undo and tl_log entries
		if ($this->strTable == 'tl_undo' && strlen($GLOBALS['TL_CONFIG']['undoPeriod']))
		{
			$this->Database->prepare("DELETE FROM tl_undo WHERE tstamp<?")
						   ->execute(intval(time() - $GLOBALS['TL_CONFIG']['undoPeriod']));
		}

		elseif ($this->strTable == 'tl_log' && strlen($GLOBALS['TL_CONFIG']['logPeriod']))
		{
			$this->Database->prepare("DELETE FROM tl_log WHERE tstamp<?")
						   ->execute(intval(time() - $GLOBALS['TL_CONFIG']['logPeriod']));
		}

		$this->reviseTable();

		// Add to clipboard
		if ($this->Input->get('act') == 'paste')
		{
			$arrClipboard = $this->Session->get('CLIPBOARD');

			$arrClipboard[$this->strTable] = array
			(
				'id' => $this->Input->get('id'),
				'childs' => $this->Input->get('childs'),
				'mode' => $this->Input->get('mode')
			);

			$this->Session->set('CLIPBOARD', $arrClipboard);
		}

		if ($this->Input->get('table') && $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] && $this->Database->fieldExists('pid', $this->strTable))
		{
			$this->procedure[] = 'pid=?';
			$this->values[] = CURRENT_ID;
		}

		// Custom filter
		if (is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter']) && count($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter']))
		{
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'] as $filter)
			{
				$this->procedure[] = $filter[0];
				$this->values[] = $filter[1];
			}
		}

		$return .= $this->panel();
		$return .= $this->treeView();

/**
 * Does not work because $this->root is modified and applied in limitMenu()
 *
		// Add another panel at the end of the page
		if ($this->root && strpos($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['panelLayout'], 'limit') !== false && ($strLimit = $this->limitMenu(true)) != false)
		{
			$return .= '

<form action="'.ampersand($this->Environment->request, true).'" class="tl_form" method="post">
<div class="tl_formbody">
<input type="hidden" name="FORM_SUBMIT" value="tl_filters_limit">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">

<div class="tl_panel_bottom">

<div class="tl_submit_panel tl_subpanel">
<input type="image" name="btfilter" id="btfilter" src="' . TL_FILES_URL . 'system/themes/' . $this->getTheme() . '/images/reload.gif" class="tl_img_submit" title="' . $GLOBALS['TL_LANG']['MSC']['apply'] . '" value="' . $GLOBALS['TL_LANG']['MSC']['apply'] . '">
</div>' . $strLimit . '

<div class="clear"></div>

</div>

</div>
</form>
';
		}
*/

		// Store the current IDs
		$session = $this->Session->getData();
		$session['CURRENT']['IDS'] = $this->current;
		$this->Session->setData($session);

		return $return;
	}
	
	
	/**
	 * Insert a new row into a database table
	 * @param array
	 */
	public function create($set=array())
	{
		if (!is_array($set))
		{
			$set = array();
		}
		
		$set['gid'] = (int) $this->Input->get('gid');
		
		parent::create($set);
	}
	
	
	/**
	 * Duplicate all child records of a duplicated record
	 * @param string
	 * @param int
	 * @param int
	 * @param int
	 */
	protected function copyChilds($table, $insertID, $id, $parentId)
	{
		$time = time();
		$copy = array();
		$cctable = array();
		$ctable = $GLOBALS['TL_DCA'][$table]['config']['ctable'];

		if (!$GLOBALS['TL_DCA'][$table]['config']['ptable'] && strlen($this->Input->get('childs')) && $this->Database->fieldExists('pid', $table))
		{
			$ctable[] = $table;
		}

		if (!is_array($ctable))
		{
			return;
		}

		// Walk through each child table
		foreach ($ctable as $v)
		{
			$this->loadDataContainer($v);
			$cctable[$v] = $GLOBALS['TL_DCA'][$v]['config']['ctable'];

			if (!$GLOBALS['TL_DCA'][$v]['config']['doNotCopyRecords'] && strlen($v))
			{
				$objCTable = $this->Database->prepare("SELECT * FROM " . $v . " WHERE pid=?" . ($this->Database->fieldExists('sorting', $v) ? " ORDER BY sorting" : ""))
											->execute($id);

				foreach ($objCTable->fetchAllAssoc() as $row)
				{
					// Exclude the duplicated record itself
					if ($v == $table && $row['id'] == $parentId)
					{
						continue;
					}

					foreach ($row as $kk=>$vv)
					{
						if ($kk == 'id')
						{
							continue;
						}

						// Reset all unique, doNotCopy and fallback fields to their default value
						if ($GLOBALS['TL_DCA'][$v]['fields'][$kk]['eval']['unique'] || $GLOBALS['TL_DCA'][$v]['fields'][$kk]['eval']['doNotCopy'] || $GLOBALS['TL_DCA'][$v]['fields'][$kk]['eval']['fallback'])
						{
							$vv = $GLOBALS['TL_DCA'][$v]['fields'][$kk]['default'] ? ((is_array($GLOBALS['TL_DCA'][$v]['fields'][$kk]['default'])) ? serialize($GLOBALS['TL_DCA'][$v]['fields'][$kk]['default']) : $GLOBALS['TL_DCA'][$v]['fields'][$kk]['default']) : '';
						}

						$copy[$v][$row['id']][$kk] = $vv;
					}

					$copy[$v][$row['id']]['pid'] = $insertID;
					$copy[$v][$row['id']]['tstamp'] = $time;
				}
			}
		}

		// Duplicate the child records
		foreach ($copy as $k=>$v)
		{
			if (count($v))
			{
				foreach ($v as $kk=>$vv)
				{
					$objInsertStmt = $this->Database->prepare("INSERT INTO " . $k . " %s")
													->set($vv)
													->execute();

					if ($objInsertStmt->affectedRows && (count($cctable[$k]) || $GLOBALS['TL_DCA'][$k]['list']['sorting']['mode'] == 5) && $kk != $parentId)
					{
						$this->copyChilds($k, $objInsertStmt->insertId, $kk, $parentId);
					}
				}
			}
		}
	}


	/**
	 * Auto-generate a form to edit the current database record
	 * @param integer
	 * @param integer
	 * @return string
	 */
	public function edit($intID=false, $ajaxId=false)
	{
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'])
		{
			$this->log('Table ' . $this->strTable . ' is not editable', 'DC_ProductData edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		if ($intID)
		{
			$this->intId = $intID;
		}

		$return = '';
		$this->values[] = $this->intId;
		$this->procedure[] = 'id=?';
		$this->blnCreateNewVersion = false;
		$this->blnEditLanguage = false;

		// Get the current record
		$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
								 ->limit(1)
								 ->executeUncached($this->intId);

		// Redirect if there is no record with the given ID
		if ($objRow->numRows < 1)
		{
			$this->log('Could not load record ID "'.$this->intId.'" of table "'.$this->strTable.'"!', 'DC_ProductData edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// ID of a language record is not allowed
		elseif ($objRow->language != '')
		{
			$this->log('Cannot edit language record ID "'.$this->intId.'" of table "'.$this->strTable.'"!', 'DC_ProductData edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$this->objActiveRecord = $objRow;

		// Load and/or change language
		if (in_array('isotope_multilingual', $this->Config->getActiveModules()))
		{
			$arrPageLanguages = $this->Database->execute("SELECT DISTINCT language FROM tl_page")->fetchEach('language');
			$this->arrLanguages = array_intersect_key($this->getLanguages(), array_flip($arrPageLanguages));

			if ($this->Input->post('FORM_SUBMIT') == 'tl_language')
			{
				$session = $this->Session->getData();

				if (in_array($this->Input->post('language'), array_keys($this->arrLanguages)))
				{
					$session['language'][$this->strTable][$this->intId] = $this->Input->post('language');

					if (strlen($this->Input->post('deleteLanguage')))
					{
						$this->Database->prepare("DELETE FROM " . $this->strTable . " WHERE pid=? AND language=?")->execute($this->intId, $this->Input->post('language'));
						unset($session['language'][$this->strTable][$this->intId]);
					}
				}
				else
				{
					unset($session['language'][$this->strTable][$this->intId]);
				}

				$this->Session->setData($session);
				$_SESSION['TL_INFO'] = '';
				$this->reload();
			}

			if (strlen($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]) && in_array($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId], array_keys($this->arrLanguages)))
			{
				$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE pid=? AND language=?")->execute($this->intId, $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]);

				if (!$objRow->numRows)
				{
					$intId = $this->Database->prepare("INSERT INTO tl_iso_products (pid,tstamp,language) VALUES (?,?,?)")->execute($this->intId, time(), $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId])->insertId;

					$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")->execute($intId);
				}

				$this->objActiveRecord = $objRow;
				$this->values = array($this->intId, $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]);
				$this->procedure = array('pid=?', 'language=?');
				$this->blnEditLanguage = true;
			}
		}

		$this->createInitialVersion($this->strTable, $this->objActiveRecord->id);

		// Change version
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'] && $this->Input->post('FORM_SUBMIT') == 'tl_version' && strlen($this->Input->post('version')))
		{
			$objData = $this->Database->prepare("SELECT * FROM tl_version WHERE fromTable=? AND pid=? AND version=?")
									  ->limit(1)
									  ->execute($this->strTable, $this->objActiveRecord->id, $this->Input->post('version'));

			if ($objData->numRows)
			{
				$data = deserialize($objData->data);

				if (is_array($data))
				{
					$this->Database->prepare("UPDATE " . $objData->fromTable . " %s WHERE id=?")
								   ->set($data)
								   ->execute($this->objActiveRecord->id);

					$this->Database->prepare("UPDATE tl_version SET active='' WHERE pid=?")
								   ->execute($this->objActiveRecord->id);

					$this->Database->prepare("UPDATE tl_version SET active=1 WHERE pid=? AND version=?")
								   ->execute($this->objActiveRecord->id, $this->Input->post('version'));

					$this->log(sprintf('Version %s of record ID %s (table %s) has been restored', $this->Input->post('version'), $this->objActiveRecord->id, $this->strTable), 'DC_ProductData edit()', TL_GENERAL);

					// Trigger the onrestore_callback
					if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onrestore_callback']))
					{
						foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onrestore_callback'] as $callback)
						{
							if (is_array($callback))
							{
								$this->import($callback[0]);
								$this->$callback[0]->$callback[1]($this->objActiveRecord->id, $this->strTable, $data, $this->Input->post('version'));
							}
						}
					}
				}
			}

			$this->reload();
		}


		// Build an array from boxes and rows
		$this->strPalette = $this->getPalette();
		$boxes = trimsplit(';', $this->strPalette);
		$legends = array();

		if (count($boxes))
		{
			foreach ($boxes as $k=>$v)
			{
				$eCount = 1;
				$boxes[$k] = trimsplit(',', $v);

				foreach ($boxes[$k] as $kk=>$vv)
				{
					if (preg_match('/^\[.*\]$/i', $vv))
					{
						++$eCount;
						continue;
					}

					if (preg_match('/^\{.*\}$/i', $vv))
					{
						$legends[$k] = substr($vv, 1, -1);
						unset($boxes[$k][$kk]);
					}

					elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['exclude'] || !is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]))
					{
						unset($boxes[$k][$kk]);
					}

					elseif ($this->blnEditLanguage && !$GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['attributes']['multilingual'])
					{
						unset($boxes[$k][$kk]);
					}
				}

				// Unset a box if it does not contain any fields
				if (count($boxes[$k]) < $eCount)
				{
					unset($boxes[$k]);
				}
			}

			$class = 'tl_tbox block';
			$fs = $this->Session->get('fieldset_states');

			// Render boxes
			foreach ($boxes as $k=>$v)
			{
				$strAjax = '';
				$blnAjax = false;
				$legend = '';

				if (isset($legends[$k]))
				{
					list($key, $cls) = explode(':', $legends[$k]);
					$legend = "\n" . '<legend onclick="AjaxRequest.toggleFieldset(this, \'' . $key . '\', \'' . $this->strTable . '\')">' . (isset($GLOBALS['TL_LANG'][$this->strTable][$key]) ? $GLOBALS['TL_LANG'][$this->strTable][$key] : $key) . '</legend>';
				}

				if (!$GLOBALS['TL_CONFIG']['oldBeTheme'])
				{
					if (isset($fs[$this->strTable][$key]))
					{
						$class .= ($fs[$this->strTable][$key] ? '' : ' collapsed');
					}
					else
					{
						$class .= (($cls && $legend) ? ' ' . $cls : '');
					}

					$return .= "\n\n" . '<fieldset' . ($key ? ' id="pal_'.$key.'"' : '') . ' class="' . $class . ($legend ? '' : ' nolegend') . '">' . $legend;
				}
				else
				{
					$return .= "\n\n" . '<div class="'.$class.'">';
				}

				// Build rows of the current box
				foreach ($v as $kk=>$vv)
				{
					if ($vv == '[EOF]')
					{
						if ($blnAjax && $this->Environment->isAjaxRequest)
						{
							return $strAjax . '<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">';
						}

						$blnAjax = false;
						$return .= "\n" . '</div>';

						continue;
					}

					if (preg_match('/^\[.*\]$/i', $vv))
					{
						$thisId = 'sub_' . substr($vv, 1, -1);
						$blnAjax = ($ajaxId == $thisId && $this->Environment->isAjaxRequest) ? true : false;
						$return .= "\n" . '<div id="'.$thisId.'">';

						continue;
					}

					$this->strField = $vv;
					$this->strInputName = $vv;
					$this->varValue = $this->objActiveRecord->$vv;

					// Call load_callback
					if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback']))
					{
						foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'] as $callback)
						{
							if (is_array($callback))
							{
								$this->import($callback[0]);
								$this->varValue = $this->$callback[0]->$callback[1]($this->varValue, $this);
							}
						}

						$this->objActiveRecord->{$this->strField} = $this->varValue;
					}

					// Build row
					$blnAjax ? $strAjax .= $this->row() : $return .= $this->row();
				}

				$class = 'tl_box block';

				if (!$GLOBALS['TL_CONFIG']['oldBeTheme'])
				{
					$return .= "\n" . '</fieldset>';
				}
				else
				{
					$return .= "\n" . '</div>';
				}
			}
		}

		$version = '';

		// Check versions
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'])
		{
			$objVersion = $this->Database->prepare("SELECT tstamp, version, username, active FROM tl_version WHERE fromTable=? AND pid=? ORDER BY version DESC")
									     ->execute($this->strTable, $this->objActiveRecord->id);

			if ($objVersion->numRows > 1)
			{
				$versions = '';

				while ($objVersion->next())
				{
					$versions .= '
  <option value="'.$objVersion->version.'"'.($objVersion->active ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['MSC']['version'].' '.$objVersion->version.' ('.$this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $objVersion->tstamp).') '.$objVersion->username.'</option>';
				}

				$version = '<form action="'.ampersand($this->Environment->request, true).'" id="tl_version" class="tl_form" method="post" style="float:right;">
<div class="tl_formbody">
<input type="hidden" name="FORM_SUBMIT" value="tl_version">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<select name="version" class="tl_select">'.$versions.'
</select>
<input type="submit" name="showVersion" id="showVersion" class="tl_submit" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['restore']).'">
</div>
</form>';
			}
		}

		// Check languages
		if (is_array($this->arrLanguages) && count($this->arrLanguages))
		{
			$arrAvailableLanguages = $this->Database->prepare("SELECT language FROM " . $this->strTable . " WHERE pid=?")->execute($this->intId)->fetchEach('language');
			$available = '';
			$undefined = '';

			foreach( $this->arrLanguages as $language => $label )
			{
				if (in_array($language, $arrAvailableLanguages))
				{
					if ($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId] == $language)
					{
						$available .= '<option value="' . $language . '" selected="selected">' . $label .'</option>';
						$_SESSION['TL_INFO'] = array($GLOBALS['TL_LANG']['MSC']['editingLanguage']);
					}
					else
					{
						$available .= '<option value="' . $language . '">' . $label . '</option>';
					}
				}
				else
				{
					$undefined .= '<option value="' . $language . '">' . $label . ' ('.$GLOBALS['TL_LANG']['MSC']['undefinedLanguage'].')' . '</option>';
				}
			}

			$version .= '<form action="'.ampersand($this->Environment->request, true).'" id="tl_language" class="tl_form" method="post" style="float:left;margin-left:20px;">
<div class="tl_formbody">
<input type="hidden" name="FORM_SUBMIT" value="tl_language">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<select name="language" class="tl_select' . (strlen($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]) ? ' active' : '') . '">
	<option value="">' . $GLOBALS['TL_LANG']['MSC']['defaultLanguage'] . '</option>'.$available.$undefined.'
</select>
<input type="submit" name="editLanguage" class="tl_submit" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['editLanguage']).'">
<input type="submit" name="deleteLanguage" class="tl_submit" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['deleteLanguage']).'" onclick="return confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteLanguageConfirm'] . '\')">
</div>
</form>';
		}

		if (strlen($version))
		{
			$version = '
<div class="tl_version_panel">

'.$version.'
<div class="clear"></div>
</div>';
		}

		// Add some buttons and end the form
		$return .= '
</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['save']).'">
<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']).'">' . (!$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] ? '
<input type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit" accesskey="n" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNcreate']).'">' : '') . ($this->Input->get('s2e') ? '
<input type="submit" name="saveNedit" id="saveNedit" class="tl_submit" accesskey="e" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNedit']).'">' : (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 4 || strlen($this->ptable) || $GLOBALS['TL_DCA'][$this->strTable]['config']['switchToEdit']) ? '
<input type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="g" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNback']).'">' : '')) .'
</div>

</div>
</form>

<script>
window.addEvent(\'domready\', function() {
  var first = $(\''.$this->strTable.'\').getElement(\'input[type="text"]\');
  if (first) first.focus();
});
</script>';

		$copyFallback = $this->blnEditLanguage ? '&nbsp;&nbsp;::&nbsp;&nbsp;<a href="'.$this->addToUrl('act=copyFallback').'" class="header_iso_copy" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['copyFallback']).'" accesskey="d" onclick="Backend.getScrollOffset();">'.($GLOBALS['TL_LANG']['MSC']['copyFallback'] ? $GLOBALS['TL_LANG']['MSC']['copyFallback'] : 'copyFallback').'</a>' : '';

		// Begin the form (-> DO NOT CHANGE THIS ORDER -> this way the onsubmit attribute of the form can be changed by a field)
		$return = $version . '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBT']).'" accesskey="b" onclick="Backend.getScrollOffset();">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>' . $copyFallback . '
</div>

<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['MSC']['editRecord'], ($this->intId ? 'ID '.$this->intId : '')).'</h2>
'.$this->getMessages().'
<form action="'.ampersand($this->Environment->request, true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '"'.(count($this->onsubmit) ? ' onsubmit="'.implode(' ', $this->onsubmit).'"' : '').'>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.specialchars($this->strTable).'">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">'.($this->noReload ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').$return;

		// Reload the page to prevent _POST variables from being sent twice
		if ($this->Input->post('FORM_SUBMIT') == $this->strTable && !$this->noReload)
		{
			$arrValues = $this->values;
			array_unshift($arrValues, time());

			// Trigger the onsubmit_callback
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback']))
			{
				foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback)
				{
					$this->import($callback[0]);
					$this->$callback[0]->$callback[1]($this);
				}
			}

			// Save the current version
			if ($this->blnCreateNewVersion && $this->Input->post('SUBMIT_TYPE') != 'auto')
			{
				$this->createNewVersion($this->strTable, $this->objActiveRecord->id);

				// Call the onversion_callback
				if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback']))
				{
					foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback'] as $callback)
					{
						$this->import($callback[0]);
						$this->$callback[0]->$callback[1]($this->strTable, $this->objActiveRecord->id, $this);
					}
				}

				$this->log(sprintf('A new version of %s ID %s has been created', $this->strTable, $this->objActiveRecord->id), 'DC_ProductData edit()', TL_GENERAL);
			}

			// Set the current timestamp (-> DO NOT CHANGE THE ORDER version - timestamp)
			$this->Database->prepare("UPDATE " . $this->strTable . " SET tstamp=? WHERE id=?")
						   ->execute(time(), $this->activeRecord->id);

			// Redirect
			if (isset($_POST['saveNclose']))
			{
				$_SESSION['TL_INFO'] = '';
				$_SESSION['TL_ERROR'] = '';
				$_SESSION['TL_CONFIRM'] = '';

				setcookie('BE_PAGE_OFFSET', 0, 0, '/');
				$this->redirect($this->getReferer());
			}

			elseif (isset($_POST['saveNedit']))
			{
				$_SESSION['TL_INFO'] = '';
				$_SESSION['TL_ERROR'] = '';
				$_SESSION['TL_CONFIRM'] = '';

				setcookie('BE_PAGE_OFFSET', 0, 0, '/');
				$strUrl = $this->addToUrl($GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['edit']['href']);

				$strUrl = preg_replace('/(&amp;)?s2e=[^&]*/i', '', $strUrl);
				$strUrl = preg_replace('/(&amp;)?act=[^&]*/i', '', $strUrl);

				$this->redirect($strUrl);
			}

			elseif (isset($_POST['saveNback']))
			{
				$_SESSION['TL_INFO'] = '';
				$_SESSION['TL_ERROR'] = '';
				$_SESSION['TL_CONFIRM'] = '';

				setcookie('BE_PAGE_OFFSET', 0, 0, '/');

				if ($this->ptable == '')
				{
					$this->redirect($this->Environment->script . '?do=' . $this->Input->get('do'));
				}
				elseif ($this->ptable == 'tl_theme' && $this->strTable == 'tl_style_sheet') # TODO: try to abstract this
				{
					$this->redirect($this->getReferer(false, $this->strTable));
				}
				else
				{
					$this->redirect($this->getReferer(false, $this->ptable));
				}
			}

			elseif (isset($_POST['saveNcreate']))
			{
				$_SESSION['TL_INFO'] = '';
				$_SESSION['TL_ERROR'] = '';
				$_SESSION['TL_CONFIRM'] = '';

				setcookie('BE_PAGE_OFFSET', 0, 0, '/');
				$strUrl = $this->Environment->script . '?do=' . $this->Input->get('do');

				if (isset($_GET['table']))
				{
					$strUrl .= '&amp;table=' . $this->Input->get('table');
				}

				// Tree view
				if ($this->treeView)
				{
					$strUrl .= '&amp;act=create&amp;mode=1&amp;pid=' . $this->intId;
				}

				// Parent view
				elseif ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 4)
				{
					$strUrl .= $this->Database->fieldExists('sorting', $this->strTable) ? '&amp;act=create&amp;mode=1&amp;pid=' . $this->intId . '&amp;id=' . $this->activeRecord->pid : '&amp;act=create&amp;mode=2&amp;pid=' . $this->activeRecord->pid;
				}

				// List view
				else
				{
					$strUrl .= strlen($this->ptable) ? '&amp;act=create&amp;mode=2&amp;pid=' . CURRENT_ID : '&amp;act=create';
				}

				$this->redirect($strUrl);
			}

			$this->reload();
		}

		// Set the focus if there is an error
		if ($this->noReload)
		{
			$return .= '

<script>
window.addEvent(\'domready\', function() {
  Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
});
</script>';
		}

		return $return;
	}


	/**
	 * Auto-generate a form to edit all records that are currently shown
	 * @param integer
	 * @param integer
	 * @return string
	 */
	public function editAll($intId=false, $ajaxId=false)
	{
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'])
		{
			$this->log('Table ' . $this->strTable . ' is not editable', 'DC_Table editAll()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$return = '';
		$this->import('BackendUser', 'User');

		// Get current IDs from session
		$session = $this->Session->getData();
		$ids = $session['CURRENT']['IDS'];

		if ($this->Environment->isAjaxRequest)
		{
			$ids = array($intId);
		}

		// Save field selection in session
		if ($this->Input->post('FORM_SUBMIT') == $this->strTable.'_all' && $this->Input->get('fields'))
		{
			$session['CURRENT'][$this->strTable] = deserialize($this->Input->post('all_fields'));
			$this->Session->setData($session);
		}

		// Add fields
		$fields = $session['CURRENT'][$this->strTable];

		if (is_array($fields) && count($fields) && $this->Input->get('fields'))
		{
			$class = 'tl_tbox block';
			$this->checkForTinyMce();

			// Walk through each record
			foreach ($ids as $id)
			{
				$this->intId = $id;
				$this->procedure = array('id=?');
				$this->values = array($this->intId);
				$this->blnCreateNewVersion = false;
				$this->strPalette = trimsplit('[;,]', $this->getPalette());

				$this->createInitialVersion($this->strTable, $this->intId);

				// Add meta fields if the current user is an administrator
				if ($this->User->isAdmin)
				{
					if ($this->Database->fieldExists('sorting', $this->strTable))
					{
						array_unshift($this->strPalette, 'sorting');
					}

					if ($this->Database->fieldExists('pid', $this->strTable))
					{
						array_unshift($this->strPalette, 'pid');
					}

					$GLOBALS['TL_DCA'][$this->strTable]['fields']['pid'] = array('label'=>&$GLOBALS['TL_LANG']['MSC']['pid'], 'inputType'=>'text', 'eval'=>array('rgxp'=>'digit'));
					$GLOBALS['TL_DCA'][$this->strTable]['fields']['sorting'] = array('label'=>&$GLOBALS['TL_LANG']['MSC']['sorting'], 'inputType'=>'text', 'eval'=>array('rgxp'=>'digit'));
				}

				// Begin current row
				$strAjax = '';
				$blnAjax = false;
				$return .= '
<div class="'.$class.'">';

				$class = 'tl_box block';
				$formFields = array();

				// Get field values
				$objValue = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
										   ->limit(1)
										   ->execute($this->intId);

				// Store the active record
				$this->objActiveRecord = $objValue;

				foreach ($this->strPalette as $v)
				{
					// Check whether field is excluded
					if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['exclude'])
					{
						continue;
					}

					if ($v == '[EOF]')
					{
						if ($blnAjax && $this->Environment->isAjaxRequest)
						{
							return $strAjax . '<input type="hidden" name="FORM_FIELDS_'.$id.'[]" value="'.specialchars(implode(',', $formFields)).'">';
						}

						$blnAjax = false;
						$return .= "\n  " . '</div>';

						continue;
					}

					if (preg_match('/^\[.*\]$/i', $v))
					{
						$thisId = 'sub_' . substr($v, 1, -1) . '_' . $id;
						$blnAjax = ($ajaxId == $thisId && $this->Environment->isAjaxRequest) ? true : false;
						$return .= "\n  " . '<div id="'.$thisId.'">';

						continue;
					}

					if (!in_array($v, $fields))
					{
						continue;
					}

					$this->strField = $v;
					$this->strInputName = $v.'_'.$this->intId;
					$formFields[] = $v.'_'.$this->intId;

					// Set default value and try to load the current value from DB
					$this->varValue = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['default'] ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['default'] : '';

					if ($objValue->$v !== false)
					{
						$this->varValue = $objValue->$v;
					}

					// Call load_callback
					if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback']))
					{
						foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'] as $callback)
						{
							$this->import($callback[0]);
							$this->varValue = $this->$callback[0]->$callback[1]($this->varValue, $this);
						}
					}

					// Re-set the current value
					$this->objActiveRecord->{$this->strField} = $this->varValue;

					// Build the current row
					$blnAjax ? $strAjax .= $this->row() : $return .= $this->row();
				}

				// Close box
				$return .= '
  <input type="hidden" name="FORM_FIELDS_'.$this->intId.'[]" value="'.specialchars(implode(',', $formFields)).'">
</div>';

				// Save record
				if ($this->Input->post('FORM_SUBMIT') == $this->strTable && !$this->noReload)
				{
					// Call onsubmit_callback
					if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback']))
					{
						foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback)
						{
							$this->import($callback[0]);
							$this->$callback[0]->$callback[1]($this);
						}
					}

					// Create a new version
					if ($this->blnCreateNewVersion && $this->Input->post('SUBMIT_TYPE') != 'auto')
					{
						$this->createNewVersion($this->strTable, $this->intId);
						$this->log(sprintf('A new version of %s ID %s has been created', $this->strTable, $this->intId), 'DC_Table editAll()', TL_GENERAL);
					}

					// Set current timestamp (-> DO NOT CHANGE ORDER version - timestamp)
					$this->Database->prepare("UPDATE " . $this->strTable . " SET tstamp=? WHERE id=?")
								   ->execute(time(), $this->intId);
				}
			}

			// Add the form
			$return = '

<h2 class="sub_headline_all">'.sprintf($GLOBALS['TL_LANG']['MSC']['all_info'], $this->strTable).'</h2>

<form action="'.ampersand($this->Environment->request, true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">'.($this->noReload ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').$return.'

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['save']).'"> 
<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']).'">
</div>

</div>
</form>';

			// Set the focus if there is an error
			if ($this->noReload)
			{
				$return .= '

<script>
window.addEvent(\'domready\', function() {
  Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
});
</script>';
			}

			// Reload the page to prevent _POST variables from being sent twice
			if ($this->Input->post('FORM_SUBMIT') == $this->strTable && !$this->noReload)
			{
				if ($this->Input->post('saveNclose'))
				{
					setcookie('BE_PAGE_OFFSET', 0, 0, '/');
					$this->redirect($this->getReferer());
				}

				$this->reload();
			}
		}

		// Else show a form to select the fields
		else
		{
			$options = '';
			$fields = array();

			// Add fields of the current table
			$fields = array_merge($fields, array_keys($GLOBALS['TL_DCA'][$this->strTable]['fields']));

			// Add meta fields if the current user is an administrator
			if ($this->User->isAdmin)
			{
				if ($this->Database->fieldExists('sorting', $this->strTable) && !in_array('sorting', $fields))
				{
					array_unshift($fields, 'sorting');
				}

				if ($this->Database->fieldExists('pid', $this->strTable) && !in_array('pid', $fields))
				{
					array_unshift($fields, 'pid');
				}
			}

			// Show all non-excluded fields
			foreach ($fields as $field)
			{
				if ($field == 'pid' || $field == 'sorting' || (!$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['exclude'] && !$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['doNotShow'] && (strlen($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['inputType']) || is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['input_field_callback']))))
				{
					$options .= '
  <input type="checkbox" name="all_fields[]" id="all_'.$field.'" class="tl_checkbox" value="'.specialchars($field).'"> <label for="all_'.$field.'" class="tl_checkbox_label">'.(strlen($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0]) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0] : $GLOBALS['TL_LANG']['MSC'][$field][0]).'</label><br>';
				}
			}

			$blnIsError = ($_POST && !count($_POST['all_fields']));

			// Return the select menu
			$return .= '

<h2 class="sub_headline_all">'.sprintf($GLOBALS['TL_LANG']['MSC']['all_info'], $this->strTable).'</h2>

<form action="'.ampersand($this->Environment->request, true).'&amp;fields=1" id="'.$this->strTable.'_all" class="tl_form" method="post">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'_all">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">'.($blnIsError ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').'

<div class="tl_tbox block">
<fieldset class="tl_checkbox_container">
  <legend'.($blnIsError ? ' class="error"' : '').'>'.$GLOBALS['TL_LANG']['MSC']['all_fields'][0].'</legend>
  <input type="checkbox" id="check_all" class="tl_checkbox" onclick="Backend.toggleCheckboxes(this)"> <label for="check_all" style="color:#a6a6a6;"><em>'.$GLOBALS['TL_LANG']['MSC']['selectAll'].'</em></label><br>'.$options.'
</fieldset>'.($blnIsError ? '
<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['all_fields'].'</p>' : (($GLOBALS['TL_CONFIG']['showHelp'] && strlen($GLOBALS['TL_LANG']['MSC']['all_fields'][1])) ? '
<p class="tl_help tl_tip">'.$GLOBALS['TL_LANG']['MSC']['all_fields'][1].'</p>' : '')).'
</div>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['continue']).'">
</div>

</div>
</form>';
		}

		// Return
		return '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBT']).'" accesskey="b" onclick="Backend.getScrollOffset();">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>'.$return;
	}


	/**
	 * Auto-generate a form to override all records that are currently shown
	 * @author Based on a patch by Andreas Schempp
	 * @return string
	 */
	public function overrideAll()
	{
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'])
		{
			$this->log('Table ' . $this->strTable . ' is not editable', 'DC_Table overrideAll()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$return = '';
		$this->import('BackendUser', 'User');

		// Get current IDs from session
		$session = $this->Session->getData();
		$ids = $session['CURRENT']['IDS'];

		// Save field selection in session
		if ($this->Input->post('FORM_SUBMIT') == $this->strTable.'_all' && $this->Input->get('fields'))
		{
			$session['CURRENT'][$this->strTable] = deserialize($this->Input->post('all_fields'));
			$this->Session->setData($session);
		}

		// Add fields
		$fields = $session['CURRENT'][$this->strTable];

		if (is_array($fields) && count($fields) && $this->Input->get('fields'))
		{
			$class = 'tl_tbox block';
			$formFields = array();
			$this->checkForTinyMce();

			// Save record
			if ($this->Input->post('FORM_SUBMIT') == $this->strTable)
			{
				foreach ($ids as $id)
				{
					$this->intId = $id;
					$this->procedure = array('id=?');
					$this->values = array($this->intId);
					$this->blnCreateNewVersion = false;

					$this->createInitialVersion($this->strTable, $this->intId);

					$this->strPalette = trimsplit('[;,]', $this->getPalette());

					// Store all fields
					foreach ($fields as $v)
					{
						// Check whether field is excluded or not in palette
						if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['exclude'] || !in_array($v, $this->strPalette))
						{
							continue;
						}

						$this->strField = $v;
						$this->strInputName = $v;
						$this->varValue = '';

						// Make sure the new value is applied
						$GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['eval']['alwaysSave'] = true;

						// Store value
						$this->row();
					}

					// Post processing
					if (!$this->noReload)
					{
						// Call onsubmit_callback
						if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback']))
						{
							foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback)
							{
								$this->import($callback[0]);
								$this->$callback[0]->$callback[1]($this);
							}
						}

						// Create a new version
						if ($this->blnCreateNewVersion)
						{
							$this->createNewVersion($this->strTable, $this->intId);
							$this->log(sprintf('A new version of record ID %s (table %s) has been created', $this->intId, $this->strTable), 'DC_Table editAll()', TL_GENERAL);
						}

						// Set current timestamp (-> DO NOT CHANGE ORDER version - timestamp)
						$this->Database->prepare("UPDATE " . $this->strTable . " SET tstamp=? WHERE id=?")
									   ->execute(time(), $this->intId);
					}
				}
			}

			// Begin current row
			$return .= '
<div class="'.$class.'">';

			foreach ($fields as $v)
			{
				// Check whether field is excluded
				if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['exclude'])
				{
					continue;
				}

				$formFields[] = $v;

				$this->intId = 0;
				$this->procedure = array('id=?');
				$this->values = array($this->intId);
				$this->strField = $v;
				$this->strInputName = $v;
				$this->varValue = '';

				// Disable auto-submit
				$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['submitOnChange'] = false;
				$return .= $this->row();
			}

			// Close box
			$return .= '
<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars(implode(',', $formFields)).'">
</div>';

			// Add the form
			$return = '

<h2 class="sub_headline_all">'.sprintf($GLOBALS['TL_LANG']['MSC']['all_info'], $this->strTable).'</h2>

<form action="'.ampersand($this->Environment->request, true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">'.($this->noReload ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').$return.'

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['save']).'"> 
<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']).'">
</div>

</div>
</form>';

			// Set the focus if there is an error
			if ($this->noReload)
			{
				$return .= '

<script>
window.addEvent(\'domready\', function() {
  Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
});
</script>';
			}

			// Reload the page to prevent _POST variables from being sent twice
			if ($this->Input->post('FORM_SUBMIT') == $this->strTable && !$this->noReload)
			{
				if ($this->Input->post('saveNclose'))
				{
					setcookie('BE_PAGE_OFFSET', 0, 0, '/');
					$this->redirect($this->getReferer());
				}

				$this->reload();
			}
		}

		// Else show a form to select the fields
		else
		{
			$options = '';
			$fields = array();

			// Add fields of the current table
			$fields = array_merge($fields, array_keys($GLOBALS['TL_DCA'][$this->strTable]['fields']));

			// Add meta fields if the current user is an administrator
			if ($this->User->isAdmin)
			{
				if ($this->Database->fieldExists('sorting', $this->strTable) && !in_array('sorting', $fields))
				{
					array_unshift($fields, 'sorting');
				}

				if ($this->Database->fieldExists('pid', $this->strTable) && !in_array('pid', $fields))
				{
					array_unshift($fields, 'pid');
				}
			}

			// Show all non-excluded fields
			foreach ($fields as $field)
			{
				if ($field == 'pid' || $field == 'sorting' || (!$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['exclude'] && !$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['doNotShow'] && (strlen($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['inputType']) || is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['input_field_callback']))))
				{
					$options .= '
  <input type="checkbox" name="all_fields[]" id="all_'.$field.'" class="tl_checkbox" value="'.specialchars($field).'"> <label for="all_'.$field.'" class="tl_checkbox_label">'.(strlen($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0]) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0] : $GLOBALS['TL_LANG']['MSC'][$field][0]).'</label><br>';
				}
			}

			$blnIsError = ($_POST && !count($_POST['all_fields']));

			// Return the select menu
			$return .= '

<h2 class="sub_headline_all">'.sprintf($GLOBALS['TL_LANG']['MSC']['all_info'], $this->strTable).'</h2>

<form action="'.ampersand($this->Environment->request, true).'&amp;fields=1" id="'.$this->strTable.'_all" class="tl_form" method="post">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'_all">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">'.($blnIsError ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').'

<div class="tl_tbox block">
<fieldset class="tl_checkbox_container">
  <legend'.($blnIsError ? ' class="error"' : '').'>'.$GLOBALS['TL_LANG']['MSC']['all_fields'][0].'</legend>
  <input type="checkbox" id="check_all" class="tl_checkbox" onclick="Backend.toggleCheckboxes(this)"> <label for="check_all" style="color:#a6a6a6;"><em>'.$GLOBALS['TL_LANG']['MSC']['selectAll'].'</em></label><br>'.$options.'
</fieldset>'.($blnIsError ? '
<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['all_fields'].'</p>' : (($GLOBALS['TL_CONFIG']['showHelp'] && strlen($GLOBALS['TL_LANG']['MSC']['all_fields'][1])) ? '
<p class="tl_help tl_tip">'.$GLOBALS['TL_LANG']['MSC']['all_fields'][1].'</p>' : '')).'
</div>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['continue']).'">
</div>

</div>
</form>';
		}

		// Return
		return '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBT']).'" accesskey="b" onclick="Backend.getScrollOffset();">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>'.$return;
	}


	/**
	 * List all records of the current table as tree and return them as HTML string
	 * @return string
	 */
	protected function treeView()
	{
		// Return if there is no parent table
		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] != 5)
		{
			return '
<p class="tl_empty">DC_ProductData does only support sorting mode 5!</p>';
		}

		$table = $this->strTable;
		$treeClass = 'tl_tree tl_productdata';
		$orderBy = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'];
		$firstOrderBy = preg_replace('/\s+.*$/i', '', $orderBy[0]);

		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6)
		{
			$table = $this->ptable;
			$treeClass = 'tl_tree_xtnd tl_productdata';

			$this->loadLanguageFile($table);
			$this->loadDataContainer($table);
		}

		if (is_array($this->orderBy) && strlen($this->orderBy[0]))
		{
			$orderBy = $this->orderBy;
			$firstOrderBy = $this->firstOrderBy;
		}


		$query = "SELECT id FROM " . $this->strTable . " WHERE pid=0";

		if (count($this->procedure))
		{
			$query .= " AND " . implode(' AND ', $this->procedure);
		}

		if (is_array($this->root) && count($this->root) > 0)
		{
			$query .= " AND id IN(" . implode(',', array_map('intval', $this->root)) . ")";
		}

		if (is_array($orderBy) && strlen($orderBy[0]))
		{
			foreach ($orderBy as $k=>$v)
			{
				if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['eval']['findInSet'])
				{
					$keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$v]['options'];

					if (array_is_assoc($keys))
					{
						$keys = array_keys($keys);
					}

					$orderBy[$k] = "FIND_IN_SET(" . $v . ", '" . implode(',', $keys) . "')";
				}
			}

			$query .= " ORDER BY " . implode(', ', $orderBy);
		}

		if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] % 2) == 0)
		{
			$query .= " DESC";
		}

		$objRowStmt = $this->Database->prepare($query);

		if (strlen($this->limit))
		{
			$arrLimit = explode(',', $this->limit);
			$objRowStmt->limit($arrLimit[1], $arrLimit[0]);
		}

		$objIds = $objRowStmt->execute($this->values);

		if ($objIds->numRows)
		{
			$this->root = $objIds->fetchEach('id');
		}

		// Get session data and toggle nodes
		if ($this->Input->get('ptg') == 'all')
		{
			$session = $this->Session->getData();
			$node = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $this->strTable.'_'.$table.'_tree' : $this->strTable.'_tree';

			// Expand tree
			if (!is_array($session[$node]) || count($session[$node]) < 1 || current($session[$node]) != 1)
			{
				$session[$node] = array();
				$objNodes = $this->Database->execute("SELECT DISTINCT pid FROM " . $table . " WHERE pid>0");

				while ($objNodes->next())
				{
					$session[$node][$objNodes->pid] = 1;
				}
			}

			// Collapse tree
			else
			{
				$session[$node] = array();
			}

			$this->Session->setData($session);
			$this->redirect(preg_replace('/(&(amp;)?|\?)ptg=[^& ]*/i', '', $this->Environment->request));
		}

		// Return if a mandatory field (id, pid) is missing
		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && (!$this->Database->fieldExists('id', $table) || !$this->Database->fieldExists('pid', $table)))
		{
			return '
<p class="tl_empty">strTable "'.$table.'" can not be shown as tree!</p>';
		}

		// Return if there is no parent table
		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6 && !strlen($this->ptable))
		{
			return '
<p class="tl_empty">strTable "'.$table.'" can not be shown as extended tree!</p>';
		}

		$blnClipboard = false;
		$arrClipboard = $this->Session->get('CLIPBOARD');

		// Check clipboard
		if (isset($arrClipboard[$this->strTable]) && count($arrClipboard[$this->strTable]))
		{
			$blnClipboard = true;
			$arrClipboard = $arrClipboard[$this->strTable];
		}

		$label = $GLOBALS['TL_DCA'][$table]['config']['label'];
		$icon = strlen($GLOBALS['TL_DCA'][$table]['list']['sorting']['icon']) ? $GLOBALS['TL_DCA'][$table]['list']['sorting']['icon'] : 'pagemounts.gif';
		$label = $this->generateImage($icon).' <label>'.$label.'</label>';

		// Begin buttons container
		$return = '
<div id="tl_buttons">'.(($this->Input->get('act') == 'select') ? '
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBT']).'" accesskey="b" onclick="Backend.getScrollOffset();">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>' : '') . (($this->Input->get('act') != 'select' && !$GLOBALS['TL_DCA'][$this->strTable]['config']['closed']) ? '
<a href="'.$this->addToUrl('act=paste&amp;mode=create').'" class="header_new" title="'.specialchars($GLOBALS['TL_LANG'][$this->strTable]['new'][1]).'" accesskey="n" onclick="Backend.getScrollOffset();">'.$GLOBALS['TL_LANG'][$this->strTable]['new'][0].'</a>' : '') . (($this->Input->get('act') != 'select') ? $this->generateGlobalButtons() . ($blnClipboard ? ' &nbsp; :: &nbsp; <a href="'.$this->addToUrl('clipboard=1').'" class="header_clipboard" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['clearClipboard']).'" accesskey="x">'.$GLOBALS['TL_LANG']['MSC']['clearClipboard'].'</a>' : '') : '') . '
</div>' . $this->getMessages(true);

		$tree = '';
		$blnHasSorting = $this->Database->fieldExists('sorting', $table);

		// Call a recursive function that builds the tree
		for ($i=0; $i<count($this->root); $i++)
		{
			$tree .= $this->generateTree($table, $this->root[$i], array('p'=>$this->root[($i-1)], 'n'=>$this->root[($i+1)]), $blnHasSorting, -20, ($blnClipboard ? $arrClipboard : false));
		}

		// Return if there are no records
		if (!strlen($tree) && $this->Input->get('act') != 'paste')
		{
			return $return . '
<p class="tl_empty">'.$GLOBALS['TL_LANG']['MSC']['noResult'].'</p>';
		}

		$return .= (($this->Input->get('act') == 'select') ? '

<form action="'.ampersand($this->Environment->request, true).'" id="tl_select" class="tl_form" method="post">
<div class="tl_formbody">
<input type="hidden" name="FORM_SUBMIT" value="tl_select">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">' : '').'

<div class="tl_listing_container tree_view" id="tl_listing">'.(isset($GLOBALS['TL_DCA'][$table]['list']['sorting']['breadcrumb']) ? $GLOBALS['TL_DCA'][$table]['list']['sorting']['breadcrumb'] : '').(($this->Input->get('act') == 'select') ? '

<div class="tl_select_trigger">
<label for="tl_select_trigger" class="tl_select_label">'.$GLOBALS['TL_LANG']['MSC']['selectAll'].'</label> <input type="checkbox" id="tl_select_trigger" onclick="Backend.toggleCheckboxes(this)" class="tl_tree_checkbox">
</div>' : '').'

<ul class="tl_listing ' . $treeClass . '">
  <li class="tl_folder_top"><div class="tl_left">'.$label.'</div> <div class="tl_right">';

		$_buttons = '&nbsp;';

		// Show paste button only if there are no root records specified
		if ($this->Input->get('act') != 'select' && $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $blnClipboard && ((!count($GLOBALS['TL_DCA'][$table]['list']['sorting']['root']) && $GLOBALS['TL_DCA'][$table]['list']['sorting']['root'] !== false) || $GLOBALS['TL_DCA'][$table]['list']['sorting']['rootPaste']))
		{
			// Call paste_button_callback (&$dc, $row, $table, $cr, $childs, $previous, $next)
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback']))
			{
				$strClass = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][0];
				$strMethod = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][1];

				$this->import($strClass);
				$_buttons = $this->$strClass->$strMethod($this, array('id'=>0), $table, false, $arrClipboard);
			}
			else
			{
				$imagePasteInto = $this->generateImage('pasteinto.gif', $GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][0], 'class="blink"');
				$_buttons = '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid=0'.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][0]).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
			}
		}

		// End table
		$return .= $_buttons . '</div><div style="clear:both;"></div></li>'.$tree.'
</ul>

</div>';

		// Close form
		if ($this->Input->get('act') == 'select')
		{
			$return .= '

<div class="tl_formbody_submit" style="text-align:right;">

<div class="tl_submit_container">' . (!$GLOBALS['TL_DCA'][$this->strTable]['config']['notDeletable'] ? '
  <input type="submit" name="delete" id="delete" class="tl_submit" accesskey="d" onclick="return confirm(\''.$GLOBALS['TL_LANG']['MSC']['delAllConfirm'].'\');" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['deleteSelected']).'"> ' : '') . '
  <input type="submit" name="cut" id="cut" class="tl_submit" accesskey="x" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['moveSelected']).'"> 
  <input type="submit" name="copy" id="copy" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['copySelected']).'"> ' . (!$GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'] ? '
  <input type="submit" name="override" id="override" class="tl_submit" accesskey="v" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['overrideSelected']).'"> 
  <input type="submit" name="edit" id="edit" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['editSelected']).'"> ' : '') . '
</div>

</div>
</div>
</form>';
		}

		return $return;
	}


	/**
	 * Generate a particular subpart of the tree and return it as HTML string
	 * @param integer
	 * @param integer
	 * @return string
	 */
	public function ajaxTreeView($id, $level)
	{
		if (!$this->Environment->isAjaxRequest)
		{
			return '';
		}

		$return = '';
		$table = $this->strTable;
		$blnPtable = false;

		// Load parent table
		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6)
		{
			$table = $this->ptable;

			$this->loadLanguageFile($table);
			$this->loadDataContainer($table);

			$blnPtable = true;
		}

		$blnProtected = false;

		// Check protected pages
		if ($table == 'tl_page')
		{
			$objParent = $this->getPageDetails($id);
			$blnProtected = $objParent->protected ? true : false;
		}

		$margin = ($level * 20);
		$hasSorting = $this->Database->fieldExists('sorting', $table);
		$arrIds = array();

		// Get records
		$objRows = $this->Database->prepare("SELECT id FROM " . $table . " WHERE pid=? AND language=''" . ($hasSorting ? " ORDER BY sorting" : ""))
							 	  ->execute($id);

		while ($objRows->next())
		{
			$arrIds[] = $objRows->id;
		}

		$blnClipboard = false;
		$arrClipboard = $this->Session->get('CLIPBOARD');

		// Check clipboard
		if (isset($arrClipboard[$this->strTable]) && count($arrClipboard[$this->strTable]))
		{
			$blnClipboard = true;
			$arrClipboard = $arrClipboard[$this->strTable];
		}

		for ($i=0; $i<count($arrIds); $i++)
		{
			$return .= ' ' . trim($this->generateTree($table, $arrIds[$i], array('p'=>$arrIds[($i-1)], 'n'=>$arrIds[($i+1)]), $hasSorting, $margin, ($blnClipboard ? $arrClipboard : false), ($id == $arrClipboard ['id'] || (is_array($arrClipboard ['id']) && in_array($id, $arrClipboard ['id'])) || (!$blnPtable && !is_array($arrClipboard['id']) && in_array($id, $this->getChildRecords($arrClipboard['id'], $table)))), $blnProtected));
		}

		return $return;
	}


	/**
	 * Recursively generate the tree and return it as HTML string
	 * @param string
	 * @param integer
	 * @param array
	 * @param boolean
	 * @param integer
	 * @param array
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function generateTree($table, $id, $arrPrevNext, $blnHasSorting, $intMargin=0, $arrClipboard=false, $blnCircularReference=false, $protectedPage=false)
	{
		static $session;

		$session = $this->Session->getData();
		$node = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $this->strTable.'_'.$table.'_tree' : $this->strTable.'_tree';

		// Toggle nodes
		if ($this->Input->get('ptg'))
		{
			$session[$node][$this->Input->get('ptg')] = (isset($session[$node][$this->Input->get('ptg')]) && $session[$node][$this->Input->get('ptg')] == 1) ? 0 : 1;
			$this->Session->setData($session);

			$this->redirect(preg_replace('/(&(amp;)?|\?)ptg=[^& ]*/i', '', $this->Environment->request));
		}

		$objRow = $this->Database->prepare("SELECT * FROM " . $table . " WHERE id=?")
								 ->limit(1)
								 ->execute($id);

		// Return if there is no result
		if ($objRow->numRows < 1)
		{
			$this->Session->setData($session);
			return '';
		}

		$return = '';
		$intSpacing = 20;

		// Add the ID to the list of current IDs
		if ($this->strTable == $table)
		{
			$this->current[] = $objRow->id;
		}

		// Check whether there are child records
		if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $this->strTable != $table)
		{
			$objChilds = $this->Database->prepare("SELECT id FROM " . $table . " WHERE pid=? AND language=''" . ($blnHasSorting ? " ORDER BY sorting" : ''))
										->execute($id);

			if ($objChilds->numRows)
			{
				$childs = $objChilds->fetchEach('id');
			}
		}

		$blnProtected = false;

		// Check whether the page is protected
		if ($table == 'tl_page')
		{
			$blnProtected = ($objRow->protected || $protectedPage) ? true : false;
		}

		$session[$node][$id] = (is_int($session[$node][$id])) ? $session[$node][$id] : 0;
		$mouseover = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $table == $this->strTable) ? ' onmouseover="Theme.hoverDiv(this, 1);" onmouseout="Theme.hoverDiv(this, 0);"' : '';

		$return .= "\n  " . '<li class="'.((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $objRow->type == 'root') || $table != $this->strTable) ? 'tl_folder' : 'tl_file').'"'.$mouseover.'><div class="tl_left" style="padding-left:'.($intMargin + $intSpacing).'px;">';

		// Calculate label and add a toggle button
		$args = array();
		$folderAttribute = 'style="margin-left:20px;"';
		$showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];
		$level = ($intMargin / $intSpacing + 1);

		if (count($childs))
		{
			$folderAttribute = '';
			$img = ($session[$node][$id] == 1) ? 'folMinus.gif' : 'folPlus.gif';
			$alt = ($session[$node][$id] == 1) ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
			$return .= '<a href="'.$this->addToUrl('ptg='.$id).'" title="'.specialchars($alt).'" onclick="Backend.getScrollOffset(); return AjaxRequest.toggleStructure(this, \''.$node.'_'.$id.'\', '.$level.', '.$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'].');">'.$this->generateImage($img, '', 'style="margin-right:2px;"').'</a>';
		}

		foreach ($showFields as $k=>$v)
		{
			// Decrypt the value
			if ($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['encrypt'])
			{
				$objRow->$v = deserialize($objRow->$v);

				$this->import('Encryption');
				$objRow->$v = $this->Encryption->decrypt($objRow->$v);
			}

			if (strpos($v, ':') !== false)
			{
				list($strKey, $strTable) = explode(':', $v);
				list($strTable, $strField) = explode('.', $strTable);

				$objRef = $this->Database->prepare("SELECT " . $strField . " FROM " . $strTable . " WHERE id=?")
										 ->limit(1)
										 ->execute($objRow->$strKey);

				$args[$k] = $objRef->numRows ? $objRef->$strField : '';
			}
			elseif (in_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['flag'], array(5, 6, 7, 8, 9, 10)))
			{
				$args[$k] = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $objRow->$v);
			}
			elseif ($GLOBALS['TL_DCA'][$table]['fields'][$v]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['multiple'])
			{
				$args[$k] = strlen($objRow->$v) ? (strlen($GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0] : $v) : '';
			}
			else
			{
				$args[$k] = strlen($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$objRow->$v]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$objRow->$v] : $objRow->$v;
			}
		}

		$label = vsprintf(((strlen($GLOBALS['TL_DCA'][$table]['list']['label']['format'])) ? $GLOBALS['TL_DCA'][$table]['list']['label']['format'] : '%s'), $args);

		// Shorten label it if it is too long
		if ($GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] > 0 && $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] < strlen(strip_tags($label)))
		{
			$this->import('String');
			$label = trim($this->String->substrHtml($label, $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'])) . ' …';
		}

		$label = preg_replace('/\(\) ?|\[\] ?|\{\} ?|<> ?/i', '', $label);

		// Call label_callback ($row, $label, $this)
		if (is_array($GLOBALS['TL_DCA'][$table]['list']['label']['label_callback']))
		{
			$strClass = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][0];
			$strMethod = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][1];

			$this->import($strClass);
			$return .= $this->$strClass->$strMethod($objRow->row(), $label, $this, $folderAttribute);
		}
		else
		{
			$return .= $this->generateImage('iconPLAIN.gif', '', $folderAttribute) . ' ' . $label;
		}

		$return .= '</div> <div class="tl_right">';
		$previous = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['pp'] : $arrPrevNext['p'];
		$next = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['nn'] : $arrPrevNext['n'];
		$_buttons = '';

		// Regular buttons ($row, $table, $root, $blnCircularReference, $childs, $previous, $next)
		if ($this->strTable == $table)
		{
			$_buttons .= ($this->Input->get('act') == 'select') ? '<input type="checkbox" name="IDS[]" id="ids_'.$id.'" class="tl_tree_checkbox" value="'.$id.'">' : $this->generateButtons($objRow->row(), $table, $this->root, $blnCircularReference, $childs, $previous, $next);
		}

		// Paste buttons
		if ($arrClipboard !== false && $this->Input->get('act') != 'select')
		{
			$_buttons .= ' ';

			// Call paste_button_callback(&$dc, $row, $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next)
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback']))
			{
				$strClass = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][0];
				$strMethod = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][1];

				$this->import($strClass);
				$_buttons .= $this->$strClass->$strMethod($this, $objRow->row(), $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next);
			}

			else
			{
				$imagePasteAfter = $this->generateImage('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id), 'class="blink"');
				$imagePasteInto = $this->generateImage('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id), 'class="blink"');

				// Regular tree (on cut: disable buttons of the page all its childs to avoid circular references)
				if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5)
				{
					$_buttons .= ($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id'])) || (count($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['root']) && in_array($id, $this->root))) ? $this->generateImage('pasteafter_.gif', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
					$_buttons .= ($arrClipboard['mode'] == 'paste' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? $this->generateImage('pasteinto_.gif', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
				}

				// Extended tree
				else
				{
					$_buttons .= ($this->strTable == $table) ? (($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? $this->generateImage('pasteafter_.gif', '', 'class="blink"') : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ') : '';
					$_buttons .= ($this->strTable != $table) ? '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ' : '';
				}
			}
		}

		$return .= (strlen($_buttons) ? $_buttons : '&nbsp;') . '</div><div style="clear:both;"></div></li>';

		// Add records of the table itself
		if ($table != $this->strTable)
		{
			$objChilds = $this->Database->prepare("SELECT id FROM " . $this->strTable . " WHERE pid=?" . ($blnHasSorting ? " ORDER BY sorting" : ''))
							 			->execute($id);

			if ($objChilds->numRows)
			{
				$ids = $objChilds->fetchEach('id');

				for ($j=0; $j<count($ids); $j++)
				{
					$return .= $this->generateTree($this->strTable, $ids[$j], array('pp'=>$ids[($j-1)], 'nn'=>$ids[($j+1)]), $blnHasSorting, ($intMargin + $intSpacing + 20), $arrClipboard, false, ($j<(count($ids)-1) || count($childs)));
				}
			}
		}

		// Begin new submenu
		if (count($childs) && $session[$node][$id] == 1)
		{
			$return .= '<li class="parent" id="'.$node.'_'.$id.'"><ul class="level_'.$level.'">';
		}

		// Add records of the parent table
		if ($session[$node][$id] == 1)
		{
			if (is_array($childs))
			{
				for ($k=0; $k<count($childs); $k++)
				{
					$return .= $this->generateTree($table, $childs[$k], array('p'=>$childs[($k-1)], 'n'=>$childs[($k+1)]), $blnHasSorting, ($intMargin + $intSpacing), $arrClipboard, ((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $childs[$k] == $arrClipboard['id']) || $blnCircularReference) ? true : false), ($blnProtected || $protectedPage));
				}
			}
		}

		// Close submenu
		if (count($childs) && $session[$node][$id] == 1)
		{
			$return .= '</ul></li>';
		}

		$this->Session->setData($session);
		return $return;
	}


	/**
	 * Return a select menu that allows to sort results by a particular field
	 * @return string
	 */
	protected function sortMenu()
	{
		$sortingFields = array();

		// Get sorting fields
		foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'] as $k=>$v)
		{
			if ($v['sorting'])
			{
				$sortingFields[] = $k;
			}
		}

		// Return if there are no sorting fields
		if (!count($sortingFields))
		{
			return '';
		}

		$this->bid = 'tl_buttons_a';
		$session = $this->Session->getData();
		$orderBy = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'];
		$firstOrderBy = preg_replace('/\s+.*$/i', '', $orderBy[0]);

		// Set sorting from user input
		if ($this->Input->post('FORM_SUBMIT') == 'tl_filters')
		{
			$session['sorting'][$this->strTable] = in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->Input->post('tl_sort')]['flag'], array(2, 4, 6, 8, 10, 12)) ? $this->Input->post('tl_sort').' DESC' : $this->Input->post('tl_sort');
			$this->Session->setData($session);
		}

		// Overwrite the "orderBy" value with the session value
		elseif (strlen($session['sorting'][$this->strTable]))
		{
			$overwrite = preg_quote(preg_replace('/\s+.*$/i', '', $session['sorting'][$this->strTable]), '/');
			$orderBy = array_diff($orderBy, preg_grep('/^'.$overwrite.'/i', $orderBy));

			array_unshift($orderBy, $session['sorting'][$this->strTable]);

			$this->firstOrderBy = $overwrite;
			$this->orderBy = $orderBy;
		}

		$options_sorter = array();

		// Sorting fields
		foreach ($sortingFields as $field)
		{
			$options_label = strlen(($lbl = is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label']) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'][0] : $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['label'])) ? $lbl : $GLOBALS['TL_LANG']['MSC'][$field];

			if (is_array($options_label))
			{
				$options_label = $options_label[0];
			}

			$options_sorter[$options_label] = '  <option value="'.specialchars($field).'"'.((!strlen($session['sorting'][$this->strTable]) && $field == $firstOrderBy || $field == str_replace(' DESC', '', $session['sorting'][$this->strTable])) ? ' selected="selected"' : '').'>'.$options_label.'</option>';
		}

		// Sort by option values
		uksort($options_sorter, 'strcasecmp');

		return '

<div class="tl_sorting tl_subpanel">
<strong>' . $GLOBALS['TL_LANG']['MSC']['sortBy'] . ':</strong> 
<select name="tl_sort" id="tl_sort" class="tl_select">
'.implode("\n", $options_sorter).'
</select>
</div>';
	}


	/**
	 * Copy multilingual fields from fallback to current language
	 */
	public function copyFallback()
	{
		$session = $this->Session->getData();

		$strLanguage = $session['language'][$this->strTable][$this->intId];
		$this->strPalette = trimsplit('[;,]', $this->getPalette());

		$arrDuplicate = array();

		foreach( $this->strPalette as $field )
		{
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]) && $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['attributes']['multilingual'])
			{
				$arrDuplicate[] = $field;
			}
		}
		
		if (count($arrDuplicate))
		{
			$intLanguageId = $this->Database->execute("SELECT id FROM {$this->strTable} WHERE pid={$this->intId} AND language='$strLanguage'")->id;
			
			$this->createInitialVersion($this->strTable, $intLanguageId);
			
			$arrRow = $this->Database->execute("SELECT " . implode(',', $arrDuplicate) . " FROM {$this->strTable} WHERE id={$this->intId}")->fetchAssoc();
			$this->Database->prepare("UPDATE {$this->strTable} %s WHERE id=$intLanguageId")->set($arrRow)->executeUncached();
			
			$this->createNewVersion($this->strTable, $intLanguageId);
			$this->log(sprintf('A new version of record ID %s (table %s) has been created', $intLanguageId, $this->strTable), 'DC_ProductData copyFallback()', TL_GENERAL);
		}

		$this->redirect($this->addToUrl('act=edit'));
	}
}

