<?
include("./includes/playerconfigure.php");

$conn=mysql_connect(DB_SERVER,DB_SERVER_USERNAME,DB_SERVER_PASSWORD) or die("Unable to connect with server") ;
mysql_select_db(DB_DATABASE) or die("Unable to select database");


function day_of_week($date){  
	$day_of_week = date("D", intval($date));
	return $day_of_week;
}

$WEATHER_CHANNEL_API_KEY = 'c7e992c59005c4330236a6255131b6b0';


if($_POST["datatype"]=="sendweather")
{ 
	 $box_id = $_POST["box_id"];
	//$sql="select distinct box_city,box_country,box_postal from feature_mst inner join box_mst on (feature_mst.z_boxid_fk=box_mst.z_boxid_pk) where feature_type = 'weather'";
	$sql="select distinct box_city,box_country,box_postal from box_mst  ";
	if($box_id!="")  $sql.="  where z_boxid_pk=".$box_id;
  //echo $sql;
	$result = mysql_query($sql) or die(mysql_error());
	$finalData = "";
	
	

	while($row = mysql_fetch_array($result))
	{
	
		$city = trim($row['box_city']);
		//$boxid = $row['z_boxid_fk'];
	
		if(!$city)
			continue;
		
		//$boxid = $row['z_boxid_fk'];
	
		/* getting weather information from www.google.com using google api*/		
		$loc_code = "";
 	
    if(isset($row['box_country']) && $row['box_country'] == 'United States' && ($row['box_postal']!="" || $_GET['box_postal']!="")) {
      if($_GET['box_postal']!="")
        $loc_code = $_GET['box_postal'];
      else if($row['box_postal']!="" && strlen($row['box_postal']) == 5)
        $loc_code = $row['box_postal'];       
    }
    http://api.theweatherchannel.com/data/locsearch/washington?doctype=xml&apikey=c7e992c59005c4330236a6255131b6b0&locale=en_US
	
    if($loc_code=="")
    {
      $xml_locsearch_url = 'http://api.theweatherchannel.com/data/locsearch/'.$city.'?doctype=xml&apikey='.$WEATHER_CHANNEL_API_KEY.'&locale=en_US';
      $xml_locsearch = simplexml_load_file($xml_locsearch_url);
      //var_dump($xml_locsearch);
      $loc_code_details = $xml_locsearch->xpath("location");
      $loc_code =  $loc_code_details[0]->zip;
    }
				

     $xml_url = 'http://api.theweatherchannel.com/data/df/'.$loc_code.'?doctype=xml&apikey='.$WEATHER_CHANNEL_API_KEY.'&day=0,1,2,3';
		
    $xml = simplexml_load_file($xml_url);
	
		/*$xml = simplexml_load_file('http://www.google.com/ig/api?weather='.$city);
		
		$information = $xml->xpath("/xml_api_reply/weather/forecast_information");
		$current = $xml->xpath("/xml_api_reply/weather/current_conditions");
		$forecast_list = $xml->xpath("/xml_api_reply/weather/forecast_conditions");*/

    $forecast_list = $xml->xpath("/forecasts/forecast");  
		$process_data = "";
		$process_data = $process_data."<map>\n";

		
		/* getting next 4 days weather informatin */
	
		$i = 1;
		foreach($forecast_list as $forecast)
		{
		  $icon  = "";
      $forecastphrase = "";
      if(isset($forecast->day))
      {
        $icon = $forecast->day->icon.".png";
        $forecastphrase = $forecast->day->phrase;
      }else if(isset($forecast->night)){
        $icon  = $forecast->night->icon.".png";
        $forecastphrase = $forecast->night->phrase;
      }
      
			$process_data = $process_data."\t<entry>\n";
			$process_data = $process_data."\t\t<int>".$i."</int>\n";
			$process_data = $process_data."\t\t<model.feature.WeatherItems>\n";
			$process_data = $process_data."\t\t\t<Id>".$i."</Id>\n";
			$process_data = $process_data."\t\t\t<image>".$icon."</image>\n";
			$process_data = $process_data."\t\t\t<maxTemp>".ftoc($forecast->maxTemp,$box_id)."</maxTemp>\n";
			$process_data = $process_data."\t\t\t<minTemp>".ftoc($forecast->minTemp,$box_id)."</minTemp>\n";
			$process_data = $process_data."\t\t\t<weatherInfo>".$forecastphrase."</weatherInfo>\n";
			$process_data = $process_data."\t\t\t<weatherDate>".date("Y-m-d",intval($forecast->validDate))."</weatherDate>\n";
			$process_data = $process_data."\t\t\t<weatherDay>".day_of_week(intval($forecast->validDate))."</weatherDay>\n";
			$process_data = $process_data."\t\t</model.feature.WeatherItems>\n";
			$process_data = $process_data."\t</entry>\n";
			$i++;
		}

		$process_data = $process_data."</map>\n";
		
	
		$bsql="select distinct z_boxid_fk from feature_mst inner join box_mst on (feature_mst.z_boxid_fk=box_mst.z_boxid_pk) where feature_type = 'weather' and box_mst.box_city='".$city."'";		
		if($box_id!="")  $bsql.=" and feature_mst.z_boxid_fk=".$box_id;
		$bres=mysql_query($bsql);
		while($barr=mysql_fetch_array($bres))
		{
			savefile($city, $barr["z_boxid_fk"], $process_data);
		}	
		mysql_free_result($bres);
		
		$finalData = $finalData.$process_data; 
	}
	
	mysql_free_result($result);
}	
	mysql_close($conn);
	echo "Weather Data Sent";
	//print($finalData);
	

/* This function is used to convert from Farenhite to Celcius Unit */

	function ftoc($farenhite,$box_id)
	{
		if(!$farenhite)
			return 0;
		else
			return $farenhite;
		/*	
		if($box_id > 8.0016)
			return $farenhite;
			
		$Celcius = round(((intval(trim($farenhite)) - 32) * 5)/9);
		
		return $Celcius;
		*/
	}

/* This function is used to save the weather information to weather.xml */
	
	function savefile($city, $boxid, $data)
	{
		if(!$boxid)
			return;
			
		$folder = IMAGEFOLDER.$boxid."/updates/";
		//mkdir($folder,0777,true);
		$fp = fopen($folder.'weather.xml', 'w');
		fwrite($fp, $data);
		fclose($fp);
		addfiletozip($boxid,"weather.xml","xml");
		
		addInventoryImageToImageTable(1, "webtool/feature_weather_image/00.png", $boxid, 1);
		addInventoryImageToImageTable(2, "webtool/feature_weather_image/01.png", $boxid, 1);
		addInventoryImageToImageTable(3, "webtool/feature_weather_image/02.png", $boxid, 1);
		addInventoryImageToImageTable(4, "webtool/feature_weather_image/03.png", $boxid, 1);
		addInventoryImageToImageTable(5, "webtool/feature_weather_image/04.png", $boxid, 1);
		addInventoryImageToImageTable(6, "webtool/feature_weather_image/05.png", $boxid, 1);
		addInventoryImageToImageTable(7, "webtool/feature_weather_image/06.png", $boxid, 1);
		addInventoryImageToImageTable(8, "webtool/feature_weather_image/07.png", $boxid, 1);
		addInventoryImageToImageTable(9, "webtool/feature_weather_image/08.png", $boxid, 1);
		addInventoryImageToImageTable(10, "webtool/feature_weather_image/09.png", $boxid, 1);
		addInventoryImageToImageTable(11, "webtool/feature_weather_image/10.png", $boxid, 1);
		addInventoryImageToImageTable(12, "webtool/feature_weather_image/11.png", $boxid, 1);
		addInventoryImageToImageTable(13, "webtool/feature_weather_image/12.png", $boxid, 1);
		
		addInventoryImageToImageTable(14, "webtool/feature_weather_image/13.png", $boxid, 1);
		addInventoryImageToImageTable(15, "webtool/feature_weather_image/14.png", $boxid, 1);
		addInventoryImageToImageTable(16, "webtool/feature_weather_image/15.png", $boxid, 1);
		addInventoryImageToImageTable(17, "webtool/feature_weather_image/16.png", $boxid, 1);
		addInventoryImageToImageTable(18, "webtool/feature_weather_image/17.png", $boxid, 1);
		addInventoryImageToImageTable(19, "webtool/feature_weather_image/18.png", $boxid, 1);
		addInventoryImageToImageTable(20, "webtool/feature_weather_image/19.png", $boxid, 1);
		addInventoryImageToImageTable(21, "webtool/feature_weather_image/20.png", $boxid, 1);
		addInventoryImageToImageTable(22, "webtool/feature_weather_image/21.png", $boxid, 1);
		addInventoryImageToImageTable(23, "webtool/feature_weather_image/22.png", $boxid, 1);
		addInventoryImageToImageTable(24, "webtool/feature_weather_image/23.png", $boxid, 1);
		addInventoryImageToImageTable(25, "webtool/feature_weather_image/24.png", $boxid, 1);
		addInventoryImageToImageTable(26, "webtool/feature_weather_image/25.png", $boxid, 1);
		
		addInventoryImageToImageTable(27, "webtool/feature_weather_image/26.png", $boxid, 1);
		addInventoryImageToImageTable(28, "webtool/feature_weather_image/27.png", $boxid, 1);
		addInventoryImageToImageTable(29, "webtool/feature_weather_image/28.png", $boxid, 1);
		addInventoryImageToImageTable(30, "webtool/feature_weather_image/29.png", $boxid, 1);
		addInventoryImageToImageTable(31, "webtool/feature_weather_image/30.png", $boxid, 1);
		addInventoryImageToImageTable(32, "webtool/feature_weather_image/31.png", $boxid, 1);
		addInventoryImageToImageTable(33, "webtool/feature_weather_image/32.png", $boxid, 1);
		addInventoryImageToImageTable(34, "webtool/feature_weather_image/33.png", $boxid, 1);
		addInventoryImageToImageTable(35, "webtool/feature_weather_image/34.png", $boxid, 1);
		addInventoryImageToImageTable(36, "webtool/feature_weather_image/35.png", $boxid, 1);
		addInventoryImageToImageTable(37, "webtool/feature_weather_image/36.png", $boxid, 1);
		addInventoryImageToImageTable(38, "webtool/feature_weather_image/37.png", $boxid, 1);
		addInventoryImageToImageTable(39, "webtool/feature_weather_image/38.png", $boxid, 1);
		
		addInventoryImageToImageTable(40, "webtool/feature_weather_image/39.png", $boxid, 1);
		addInventoryImageToImageTable(41, "webtool/feature_weather_image/40.png", $boxid, 1);
		addInventoryImageToImageTable(42, "webtool/feature_weather_image/41.png", $boxid, 1);
		addInventoryImageToImageTable(43, "webtool/feature_weather_image/42.png", $boxid, 1);
		addInventoryImageToImageTable(44, "webtool/feature_weather_image/43.png", $boxid, 1);
		addInventoryImageToImageTable(45, "webtool/feature_weather_image/44.png", $boxid, 1);
		addInventoryImageToImageTable(46, "webtool/feature_weather_image/45.png", $boxid, 1);
		addInventoryImageToImageTable(47, "webtool/feature_weather_image/46.png", $boxid, 1);
		addInventoryImageToImageTable(48, "webtool/feature_weather_image/47.png", $boxid, 1);
	}
	

?>
