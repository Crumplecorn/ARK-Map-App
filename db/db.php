<?php

function generate_random_letters($length) {
  $random = '';
  for ($i = 0; $i < $length; $i++) {
    $random .= chr(rand(ord('a'), ord('z')));
  }
  return $random;
}

require("conf.php");

	error_reporting(E_NONE);

	ob_start();

	$file=fopen('log.txt', 'a');

	try {

	$db=new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true));



	$url=parse_url($_SERVER['REQUEST_URI']);
	$path=pathinfo($url['path']);
	$request_id=$path['basename'];
	$request_type=basename($path['dirname']);

	$map_id=$_GET['map_id'];
	if (!$map_id) {
		die("bad request");
	}

	if (!is_numeric($request_id)) {
		$request_type=$request_id;
		$request_id=-1;
	}

	if ($_SERVER['REQUEST_METHOD']=="PUT") {

		$putdata = fopen("php://input", "r");

		$json="";
		while ($data = fread($putdata, 1024)) {
		  	$json.=$data;
		}
		$data=json_decode($json);

		fclose($putdata);

		fwrite($file, print_r($data, TRUE)."\r\n");

		if ($request_type=="marker" and isset($_GET['create'])) {
			//CREATE A NEW MARKER
			//$db->query("Lock tables markers write");
			$query=$db->prepare("SELECT max(id) FROM markers WHERE map_id=?");
			$query->execute(array($map_id));
			$row=$query->fetch(PDO::FETCH_NUM);
			$nextid=max((int)$row[0]+1, 1000);fwrite($file, "GOT NEW MARKER ID $nextid\r\n");

			$query=$db->prepare("INSERT INTO markers (map_id, id, name, longitude, latitude, r, layer_id, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			$query->execute(array($map_id, $nextid, $data->name, $data->longitude, $data->latitude, $data->r, $data->layer_id, time()));fwrite($file, "MARKER INSERT $map_id, $nextid, $data->name\r\n");
			//$db->query("unlock tables");

			$query=$db->prepare("SELECT * FROM markers where map_id=? AND id = ?");
			$query->execute(array($map_id, $nextid));fwrite($file, "NEW MARKER RECALL\r\n");
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$marker = new stdClass();
			foreach ($row as $key=>$value) {
				$marker->$key=$value;
			}
			echo json_encode($marker);
			fwrite($file, json_encode($marker)."\r\n");
			return;
		}

		/*if ($request_type=="marker" and $request_id==-1) {
			//UPDATE ALL MARKERS

			$query=$db->prepare("DELETE FROM markers WHERE map_id=?");
			$query->execute(array($map_id));fwrite($file, "DELETE MARKERS for $map_id\r\n");

			$query=$db->prepare("INSERT INTO markers (map_id, name, x, y, r, gps, layer_id, fav, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
			foreach ($data as $id=>$marker) {
				$query->execute(array($map_id, $marker->name, $marker->x, $marker->y, $marker->r, $marker->gps, $marker->layer_id, $marker->fav, time()));fwrite($file, "INSERT MARKER $marker->name\r\n");
			}

		}*/

		if ($request_type=="marker" and $request_id>-1) {
			//UPDATE ONE MARKER

			$query=$db->prepare("UPDATE markers SET name=?, longitude=?, latitude=?, r=?, gps=?, layer_id=?, fav=?, showcoords=?, updated=?, description=? WHERE map_id=? AND id=?");
			$query->execute(array($data->name, $data->longitude, $data->latitude, $data->r, $data->gps, $data->layer_id, $data->fav, $data->showcoords, time(), $data->description, $map_id, $request_id));fwrite($file, "UPDATE MARKER $map_id, $request_id, $data->name\r\n");

			if ($query->rowCount()==0) {
				$query=$db->prepare("INSERT INTO markers (map_id, id, name, longitude, latitude, r, gps, layer_id, fav, showcoords, updated, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");	
				$query->execute(array($map_id, $request_id, $data->name, $data->longitude, $data->latitude, $data->r, $data->gps, $data->layer_id, $data->fav, $data->showcoords, time(), $data->description));fwrite($file, "UPDATE (INSERT) MARKER $map_id, $request_id, $data->name\r\n");
			}

			$query=$db->prepare("SELECT * FROM markers WHERE map_id=? AND id=?");
			$query->execute(array($map_id, $data->id));fwrite($file, "SELECT MARKER $map_id, $data->id, $data->name\r\n");
			$row=$query->fetch(PDO::FETCH_ASSOC);
			$layer=new stdClass();
			foreach ($row as $key=>$value) {
				$marker->$key=$value;
			}
			echo json_encode($marker);fwrite($file, json_encode($marker)."\r\n");
			return;

		}

		if ($request_type=="layer" and isset($_GET['create'])) {
			//CREATE A LAYER
			//$db->query("Lock tables layers write");
			$query=$db->prepare("SELECT max(id) FROM layers WHERE map_id=?");
			$query->execute(array($map_id));
			$row=$query->fetch(PDO::FETCH_NUM);
			$nextid=max((int)$row[0]+1, 100);fwrite($file, "GOT NEW LAYER ID $nextid\r\n");
			
			$query=$db->prepare("INSERT INTO layers (map_id, id, name, color, visible) VALUES (?, ?, ?, ?, ?)");
			$query->execute(array($map_id, $nextid, $data->name, $data->color, $data->visible));fwrite($file, "INSERT LAYER $map_id, $nextid, $data->name\r\n");
			//$db->query("unlock tables");

			$query=$db->prepare("SELECT * FROM layers WHERE map_id=? AND id=?");
			$query->execute(array($map_id, $nextid));fwrite($file, "SELECT LAYER $nextid\r\n");
			$row=$query->fetch(PDO::FETCH_ASSOC);
			$layer=new stdClass();
			foreach ($row as $key=>$value) {
				$layer->$key=$value;
			}
			echo json_encode($layer);fwrite($file, json_encode($layer)."\r\n");
			return;
		}

		/*if ($request_type=="layer" and $request_id==-1) {
			//UPDATE ALL LAYERS

			$query=$db->prepare("DELETE FROM layers WHERE map_id=?");
			$query->execute(array($map_id));fwrite($file, "SELETE LAYERS FOR MAP $map_id\r\n");

			$query=$db->prepare("INSERT INTO layers (map_id, id, name, color, visible) VALUES (?, ?, ?, ?, ?)");
			foreach ($data as $id=>$layer) {
				$query->execute(array($map_id, $layer->id, $layer->name, $layer->color, $layer->visible));fwrite($file, "INSERT LAYER $layer->name\r\n");
			}

		}*/

		if ($request_type=="layer" and $request_id>-1) {
			//UPDATE ONE LAYER
			$query=$db->prepare("UPDATE layers SET name=?, color=?, visible=? WHERE map_id=? AND id=?");
			$query->execute(array($data->name, $data->color, $data->visible, $map_id, $request_id));fwrite($file, "UPDATE LAYER $map_id, $request_id, $data->name\r\n");
			
			if ($query->rowCount()==0) {
				$query=$db->prepare("INSERT INTO layers (map_id, id, name, color, visible) VALUES (?, ?, ?, ?, ?)");
				$query->execute(array($map_id, $request_id, $data->name, $data->color, $data->visible));fwrite($file, "UPDATE (INSERT) LAYER $map_id, $request_id, $data->name\r\n");
			}

			$query=$db->prepare("SELECT * FROM layers WHERE map_id=? AND id=?");
			$query->execute(array($map_id, $data->id));fwrite($file, "SELECT LAYER $data->id\r\n");
			$row=$query->fetch(PDO::FETCH_ASSOC);
			$layer=new stdClass();
			foreach ($row as $key=>$value) {
				$layer->$key=$value;
			}
			echo json_encode($layer);fwrite($file, json_encode($layer)."\r\n");
			return;
		}

	}

	if ($_SERVER['REQUEST_METHOD']=="GET") {
		//GET ALL MARKERS)
		if ($request_type=="marker") {

			if (isset($_GET['getlastid'])) {
				$query=$db->prepare("SELECT max(id) FROM markers");
				$query->execute(array());
				$row=$query->fetch(PDO::FETCH_NUM);
				echo json_encode(array("lastid"=>$row[0]));
				return;
			}

			if (isset($_GET['time'])) {
				$time=$_GET['time'];
			} else {
				$time=0;
			}

			$query=$db->prepare("SELECT * FROM markers where map_id=? AND updated>?");
			$query->execute(array($map_id, $time));

			$markers = new stdClass();

			foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$marker = new stdClass();
				foreach ($row as $key=>$value) {
					$marker->$key=$value;
				}
				$markers->$row['id']=$marker;
			}

			$defaultmarkers=array(
				'0'=>array('map_id'=>$map_id, 'id'=>'0', 'name'=>'Red Obelisk', 'longitude'=>'25.6', 'latitude'=>'25.5', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'5', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'1'=>array('map_id'=>$map_id, 'id'=>'1', 'name'=>'Green Obelisk', 'longitude'=>'17.4', 'latitude'=>'79.8', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'5', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'2'=>array('map_id'=>$map_id, 'id'=>'2', 'name'=>'Blue Obelisk', 'longitude'=>'72.2', 'latitude'=>'58.9', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'5', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),

				'3'=>array('map_id'=>$map_id, 'id'=>'3', 'name'=>'Underwater Cave', 'longitude'=>'21.5', 'latitude'=>'10', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'4'=>array('map_id'=>$map_id, 'id'=>'4', 'name'=>'Underwater Cave', 'longitude'=>'39.5', 'latitude'=>'10.4', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'5'=>array('map_id'=>$map_id, 'id'=>'5', 'name'=>'Underwater Cave', 'longitude'=>'89.7', 'latitude'=>'6.3', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'6'=>array('map_id'=>$map_id, 'id'=>'6', 'name'=>'Underwater Cave', 'longitude'=>'91.5', 'latitude'=>'36.3', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'7'=>array('map_id'=>$map_id, 'id'=>'7', 'name'=>'Underwater Cave', 'longitude'=>'91.9', 'latitude'=>'52.7', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'8'=>array('map_id'=>$map_id, 'id'=>'8', 'name'=>'Underwater Cave', 'longitude'=>'89.7', 'latitude'=>'87.4', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'9'=>array('map_id'=>$map_id, 'id'=>'9', 'name'=>'Underwater Cave', 'longitude'=>'71.3', 'latitude'=>'90.3', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'10'=>array('map_id'=>$map_id, 'id'=>'10', 'name'=>'Underwater Cave', 'longitude'=>'36.8', 'latitude'=>'89.8', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'11'=>array('map_id'=>$map_id, 'id'=>'11', 'name'=>'Underwater Cave', 'longitude'=>'14', 'latitude'=>'90.8', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'12'=>array('map_id'=>$map_id, 'id'=>'12', 'name'=>'Underwater Cave', 'longitude'=>'9.9', 'latitude'=>'83', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'13'=>array('map_id'=>$map_id, 'id'=>'13', 'name'=>'Underwater Cave', 'longitude'=>'11.2', 'latitude'=>'50.5', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'14'=>array('map_id'=>$map_id, 'id'=>'14', 'name'=>'Underwater Cave', 'longitude'=>'10.1', 'latitude'=>'16', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),

				'15'=>array('map_id'=>$map_id, 'id'=>'15', 'name'=>'Cave', 'longitude'=>'19', 'latitude'=>'19.4', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'16'=>array('map_id'=>$map_id, 'id'=>'16', 'name'=>'Cave', 'longitude'=>'46.9', 'latitude'=>'41.5', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'17'=>array('map_id'=>$map_id, 'id'=>'17', 'name'=>'Cave', 'longitude'=>'85.4', 'latitude'=>'14.7', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'18'=>array('map_id'=>$map_id, 'id'=>'18', 'name'=>'Cave', 'longitude'=>'56.1', 'latitude'=>'68.3', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'19'=>array('map_id'=>$map_id, 'id'=>'19', 'name'=>'Cave', 'longitude'=>'53.5', 'latitude'=>'80.2', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
				'20'=>array('map_id'=>$map_id, 'id'=>'20', 'name'=>'Cave', 'longitude'=>'86.1', 'latitude'=>'70.6', 'r'=>'3', 'gps'=>'1', 'layer_id'=>'6', 'fav'=>'0', 'showcoords'=>'0', 'updated'=>'1', 'description'=>""),
			);

			if (!isset($_GET['time'])) { //Exclude default markers if we are polling

				foreach ($defaultmarkers as $key=>$marker) {
					if (!isset($markers->$key)) {
						$markers->$key=(object)$marker;
					}
				}

			}

			echo json_encode($markers);

		}

		if ($request_type=="layer") {
			//GET ALL LAYERS

			if (isset($_GET['getlastid']))	 {
				$query=$db->prepare("SELECT max(id) FROM layers WHERE map_id=?");
				$query->execute(array($map_id));
				$row=$query->fetch(PDO::FETCH_NUM);
				echo json_encode(array("lastid"=>$row[0]));
				return;
			}

			$query=$db->prepare("SELECT * FROM layers where map_id=?");
			$query->execute(array($map_id));

			$layers = new stdClass();

			foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$layer = new stdClass();
				foreach ($row as $key=>$value) {
					$layer->$key=$value;
				}
				$layers->$row['id']=$layer;
			}

			$defaultlayers=array(
				'0'=>array('map_id'=>$map_id, 'id'=>'0', 'name'=>'Enemy', 'color'=>'#ff0000', 'visible'=>'1'),
				'1'=>array('map_id'=>$map_id, 'id'=>'1', 'name'=>'Friendly', 'color'=>'#00ff00', 'visible'=>'1'),
				'2'=>array('map_id'=>$map_id, 'id'=>'2', 'name'=>'Minor Enemy', 'color'=>'#ffa500', 'visible'=>'1'),
				'3'=>array('map_id'=>$map_id, 'id'=>'3', 'name'=>'Neutral', 'color'=>'#ffff00', 'visible'=>'1'),
				'4'=>array('map_id'=>$map_id, 'id'=>'4', 'name'=>'Landmark', 'color'=>'#ffffff', 'visible'=>'1'),
				'5'=>array('map_id'=>$map_id, 'id'=>'5', 'name'=>'Obelisks', 'color'=>'#ffffff', 'visible'=>'0'),
				'6'=>array('map_id'=>$map_id, 'id'=>'6', 'name'=>'Caves', 'color'=>'#ffffff', 'visible'=>'0')
			);

			foreach ($defaultlayers as $key=>$layer) {
				if (!isset($layers->$key)) {
					$layers->$key=(object)$layer;
				}
			}

			echo json_encode($layers);
		}

	}

	if ($_SERVER['REQUEST_METHOD']=="DELETE") {

		if ($request_type=="marker" and $request_id>-1) {
			//DELETE MARKER

			$query=$db->prepare("DELETE FROM markers WHERE map_id=? AND id=?");
			$query->execute(array($map_id, $request_id));fwrite($file, "DELETE MARKER $map_id, $request_id\r\n");
			return;

		}

		if ($request_type=="layer" and $request_id>-1) {
			//DELETE LAYER

			$query=$db->prepare("DELETE FROM layers WHERE map_id=? AND id=?");
			$query->execute(array($map_id, $request_id));fwrite($file, "DELETE LAYER $map_id, $request_id\r\n");
			return;

		}

	}

	} catch (PDOException $ex) {
		fwrite($file, $ex->getMessage()."\r\n");
	}

	//fwrite($file, ob_get_contents());
	fclose($file);

?>