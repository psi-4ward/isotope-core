<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

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
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * USPS-specific response rate codes
 */
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['FIRST CLASS'] = '0';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['PRIORITY'] = '1';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['EXPRESS HFP'] = '2';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['EXPRESS'] = '3';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['PARCEL'] = '4';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['EXPRESS SH'] = '23';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['BPM'] = '5';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['MEDIA'] = '6';
$GLOBALS['TL_LANG']['ISO_USPS']['DOMESTIC']['RRC']['LIBRARY'] = '7';

$GLOBALS['TL_LANG']['ISO_USPS']['INTERNATIONAL']['RRC']['EXPRESS'] = '1';
$GLOBALS['TL_LANG']['ISO_USPS']['INTERNATIONAL']['RRC']['PRIORITY'] = '2';
/*
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';
$GLOBALS['TL_LANG']['ISO']['MSC']['USPS']['INTERNATIONAL']['RRC'][''] = '';*/

//INTERNATIONAL
/*1Express Mail International
2Priority Mail International
4Global Express Guaranteed (Document and Non-document)
5Global Express Guaranteed Document  cx6Global Express Guaranteed Non-Document Rectangular shape
7Global Express Guaranteed Non-Document Non-Rectangular
8Priority Mail Flat Rate Envelope
9Priority Mail Flat Rate Box
10Express Mail International Flat Rate Envelope
11Priority Mail Large Flat Rate Box
12Global Express Guaranteed Envelope
13First Class Mail International Letters
14First Class Mail International Flats
15First Class Mail International Parcels
16Priority Mail Small Flat Rate Box
21PostCards
*/


class ShippingUSPS extends IsotopeShipping
{
	protected $shipping_options = array();

	/**
	 * Origin zip code
	 * string
	 */
	protected $strOriginZip;

	/**
	 * Destination zip code
	 * string
	 */
	protected $strDestinationZip;

	/**
	 * Destination country
	 * string
	 */
	protected $strDestinationCountry;


	protected $strShippingMode;


	protected $strAPIMode = 'RateV3';

	/**
	 * Weight data (pounds, ounces)
	 * array
	 */
	protected $arrWeightData = array();


	/**
	 * Return an object property
	 *
	 * @access public
	 * @param string
	 * @return mixed
	 */
	public function __get($strKey)
	{

		switch( $strKey )
		{
			case 'price':
				/*$arrDestination = array
				(
					'name'			=> $this->Isotope->Cart->shippingAddress['firstname'] . ' ' . $this->Isotope->Cart->shippingAddress['lastname'],
					'phone'			=> $this->Isotope->Cart->shippingAddress['phone'],
					'company'		=> $this->Isotope->Cart->shippingAddress['company'],
					'street'		=> $this->Isotope->Cart->shippingAddress['street_1'],
					'street2'		=> $this->Isotope->Cart->shippingAddress['street_2'],
					'street3'		=> $this->Isotope->Cart->shippingAddress['street_3'],
					'city'			=> $this->Isotope->Cart->shippingAddress['city'],
					'state'			=> $this->Isotope->Cart->shippingAddress['subdivision'],
					'zip'			=> $this->Isotope->Cart->shippingAddress['postal'],
					'country'		=> $this->Isotope->Cart->shippingAddress['country']
				);*/

				/*$arrOrigin = array
				(
					'name'			=> $this->Isotope->Config->firstname . ' ' . $this->Isotope->Config->lastname,
					'phone'			=> $this->Isotope->Config->phone,
					'company'		=> $this->Isotope->Config->company,
					'street'		=> $this->Isotope->Config->street_1,
					'street2'		=> $this->Isotope->Config->street_2,
					'street3'		=> $this->Isotope->Config->street_3,
					'city'			=> $this->Isotope->Config->city,
					'state'			=> $this->Isotope->Config->state,
					'zip'			=> $this->Isotope->Config->postal,
					'country'		=> $this->Isotope->Config->country
				);*/

				$arrCountries = $this->getCountries();
				$destCountryText = $arrCountries[$this->Isotope->Cart->shippingAddress['country']];

				$this->strOriginZip = $this->Isotope->Config->postal;
				$this->strDestinationZip = $this->Isotope->Cart->shippingAddress['postal'];
				$this->strDestinationCountry = $this->Isotope->Cart->shippingAddress['country'];
				$this->strDestinationCountryText = ($this->Isotope->Cart->shippingAddress['country']=='uk') ? 'Great Britain' : $destCountryText;
				$this->strShippingMode = $this->getShippingMode($this->Isotope->Cart->shippingAddress['country']);
				$this->blnDomestic = ($this->Isotope->Cart->shippingAddress['country']!='us' ? false : true);

				if(!$this->blnDomestic)
				{
					$this->strAPIMode = 'IntlRate';
				}

				$fltWeight = $this->Isotope->Cart->getShippingWeight('lb');

				$arrWeight = explode('.', (string)$fltWeight);

				$this->arrWeightData = array
				(
					'pounds'		=> $arrWeight[0],
					'ounces'		=> ((integer)$arrWeight[1] / 100) * 16
				);

				return $this->calculateShippingRate();
				break;
		}

		return parent::__get($strKey);

	}


	public function calculateShippingRate()
	{
		if($_SESSION['CHECKOUT_DATA']['shipping']['modules'][$this->id]['price'])	//to avoid calling the CURL multiple times which slows us down.
		{
			 $fltPrice = $_SESSION['CHECKOUT_DATA']['shipping']['modules'][$this->id]['price'];
		}
		else
		{
			 $userName = $this->usps_userName; // Your USPS Username
			 $orig_zip = $this->strOriginZip; // Zipcode you are shipping FROM
			 $dest_zip = $this->strDestinationZip; // Zipcode you are shipping TO
			 $dest_country = $this->strDestinationCountry;  // Country you are shipping TO
			 $dest_countryText = $this->strDestinationCountryText;
			 $shipMode = $this->strShippingMode;
			 $blnDomestic = $this->blnDomestic;

			 $url = "http://production.shippingapis.com/ShippingAPI.dll";
			 $ch = curl_init();

			 // set the target url
			 curl_setopt($ch, CURLOPT_URL,$url);
			 curl_setopt($ch, CURLOPT_HEADER, 1);
			 curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

			 // parameters to post
			 curl_setopt($ch, CURLOPT_POST, 1);

			 if(!$blnDomestic) //INTERNATIONAL SHIPPING
			 {
			 	$data = "API=" . $this->strAPIMode . "&XML=<" . $this->strAPIMode . "Request USERID=\"" . $userName . "\"><Package ID=\"1ST\"><Pounds>" . $this->arrWeightData['pounds'] . "</Pounds><Ounces>" . $this->arrWeightData['ounces'] . "</Ounces><MailType>Package</MailType><Country>". $dest_countryText  ."</Country></Package></" . $this->strAPIMode . "Request>";
			 }else
			 {
			 	$data = "API=" . $this->strAPIMode . "&XML=<" . $this->strAPIMode . "Request USERID=\"" . $userName . "\"><Package ID=\"1ST\"><Service>" . $this->usps_enabledService . "</Service><ZipOrigination>" . $orig_zip . "</ZipOrigination><ZipDestination>" . $dest_zip . "</ZipDestination><Pounds>" . $this->arrWeightData['pounds'] . "</Pounds><Ounces>" . $this->arrWeightData['ounces'] . "</Ounces><Size>REGULAR</Size><Machinable>TRUE</Machinable></Package></" . $this->strAPIMode . "Request>";
			 }

			// send the POST values to USPS
			curl_setopt($ch, CURLOPT_POSTFIELDS,$data);

			$result=curl_exec ($ch);

			$data = strstr($result, '<?');

			 //echo '<!-- '. $data. ' -->'; // Uncomment to show XML in comments
			$xml_parser = xml_parser_create();
			xml_parse_into_struct($xml_parser, $data, $vals, $index);
			xml_parser_free($xml_parser);
			$params = array();
			$level = array();
			foreach ($vals as $xml_elem) {
				if ($xml_elem['type'] == 'open') {
					if (array_key_exists('attributes',$xml_elem)) {
						list($level[$xml_elem['level']],$extra) = array_values($xml_elem['attributes']);
					} else {
					$level[$xml_elem['level']] = $xml_elem['tag'];
					}
				}
				if ($xml_elem['type'] == 'complete') {
				$start_level = 1;
				$php_stmt = '$params';
				while($start_level < $xml_elem['level']) {
					$php_stmt .= '[$level['.$start_level.']]';
					$start_level++;
				}
				$php_stmt .= '[$xml_elem[\'tag\']] = $xml_elem[\'value\'];';
				eval($php_stmt);
				}
			}

			curl_close($ch);

			//Need to uppercase the strAPImode
			$strAPItext = strtoupper($this->strAPIMode) . 'RESPONSE';

			//echo '<pre>'; print_r($params); echo'</pre>'; // Uncomment to see xml tags

			$strRate = ($blnDomestic ? 'RATE' : 'POSTAGE');
			$fltPrice = $params[strtoupper($this->strAPIMode) . 'RESPONSE']['1ST'][$GLOBALS['TL_LANG']['ISO_USPS'][$shipMode]['RRC'][$this->usps_enabledService]][$strRate];
			$_SESSION['CHECKOUT_DATA']['shipping']['modules'][$this->id]['price'] = $fltPrice;
		}

		return $fltPrice;
	}

	public function getShippingMode($strCountry)
	{
		return ($strCountry=='us' ? 'DOMESTIC' : 'INTERNATIONAL');
	}


	/**
	 * Get the checkout surcharge for this shipping method
	 */
	public function getSurcharge($objCollection)
	{
		$fltPrice = $this->price;

		if ($fltPrice == 0)
		{
			return false;
		}

		return $this->Isotope->calculateSurcharge(
								$fltPrice,
								($GLOBALS['TL_LANG']['MSC']['shippingLabel'] . ' (' . $this->label . ')'),
								$this->arrData['tax_class'],
								$objCollection->getProducts(),
								$this);
	}
}
