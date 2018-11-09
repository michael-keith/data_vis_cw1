<?php

// For some reason, this site (/www.skyscrapercenter.com) does all the templating work client side and each search page contains the full list of the all buildings as a javascript array or JSON objects, so no crawler is required for pagination. However a load of convoluted regex stuff is required to convert the JS array.

//DB
$db = new PDO('mysql:host=localhost;dbname=DBNAME;charset=utf8mb4', 'DBUSER', 'DBPASS');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$html = file_get_contents("http://www.skyscrapercenter.com/compare-data/submit?type%5B%5D=building&status%5B%5D=COM&status%5B%5D=UC&status%5B%5D=UCT&status%5B%5D=STO&status%5B%5D=OH&status%5B%5D=NC&status%5B%5D=PRO&status%5B%5D=DEM&base_region=0&base_country=0&base_city=942&base_height_range=0&base_company=All&base_min_year=1900&base_max_year=9999&comp_region=0&comp_country=0&comp_city=0&comp_height_range=4&comp_company=All&comp_min_year=1960&comp_max_year=2019&skip_comparison=on&output%5B%5D=list&dataSubmit=Show+Results");

//Extract the building JS
preg_match_all("/var buildings \= \[(.*?)\]\;/s", $html, $matches);
$building_json = preg_replace("/var buildings = \[/s", "", $matches[0] );
//Extract the json strings from the JS array
preg_match_all("/\{(.*?)\}/s", $building_json[0], $matches);

//Init buildings
foreach($matches[0] as $match) {
  $building_json = json_decode($match);
  echo $building_json->name . "\n";
  $building = new Building($building_json, $db);
  $building->save();
}

// Building class (mostly for clarity rather than doing everything inline in the for loop)
class Building {

  // Just do everything in the constructor, why not?
  // Looking back at this now a lot of this variable setting is redundant as I could have just directly used the json object.
  public function __construct($building_json, $db) {
    $this->db = $db;

    $this->city = $building_json->city;
    $this->name = $building_json->name;
    $this->status = $building_json->status;

    $this->height = $building_json->height_architecture_formatted;
    if($this->height == "-"){$this->height = NULL;}

    $this->function = $building_json->functions;

    $this->construction_start = $building_json->start;
    if($this->construction_start == "-"){$this->construction_start = NULL;}

    $this->completed = $building_json->completed;
    if($this->completed == "-"){$this->completed = NULL;}

    $this->lat = $building_json->latitude;
    $this->lon = $building_json->longitude;

    $this->link = $building_json->url;

    $this->get_additionals();
  }

  //Scrape the individual pages for data.
  public function get_additionals() {
    $page = file_get_contents("http://www.skyscrapercenter.com" . $this->link);

    preg_match_all("/<td>Postal Code<\/td>(.*?)<\/td>/s", $page, $matches);
    $this->postcode = trim(str_replace("<td>", "", $matches[1])[0]);

    preg_match_all("/<a id='map-trigger' href='.+'>(.*?)<\/a>/", $page, $matches);
    $this->address = $matches[1];

    preg_match_all("/Proposed<\/a><\/td>(.*?)<\/td>/s", $page, $matches);
    $this->proposed = trim(str_replace("<td>", "", $matches[1])[0]);
    if(!$this->proposed){$this->proposed = NULL;}

    preg_match_all("/Owner\s+<\/td>(.*?)<\/td>/s", $page, $matches);
    $this->owner = trim(str_replace("<td>", "", $matches[1])[0]);
    if(!$this->owner){$this->owner = NULL;}

    preg_match_all("/Design<\/a>\s+<\/span>\s+<\/td>\s+<td>(.*?)<\/td>/s", $page, $matches);
    $this->architect = strip_tags( trim($matches[1][0]));
    if(!$this->architect){$this->architect = NULL;}

    preg_match_all("/Developer\s+<\/td>\s+<td>\(.*?)<\/td>/s", $page, $matches);
    $this->developer = strip_tags( trim($matches[1][0]));
    $this->developer = preg_replace("/^\s+/", "", $this->developer);
    if(!$this->developer){$this->developer = NULL;}

  } 

  public function save() {
    $stmt = $this->db->prepare('INSERT INTO buildings(city,name,status,postcode,address,lon,lat,height,function,proposed,construction_start,completed,owner,architect,developer)
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute(array(
      $this->city,
      $this->name,
      $this->status,
      $this->postcode,
      $this->address,
      $this->lon,
      $this->lat,
      $this->height,
      $this->function,
      $this->proposed,
      $this->construction_start,
      $this->completed,
      $this->owner,
      $this->architect,
      $this->developer
    ));
  }

}
