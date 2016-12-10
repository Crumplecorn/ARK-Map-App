function roundto1(num) {
	return Math.round((num + 0.00001) * 10) / 10;
}

		var mapController=angular.module('MapControllers', []).controller('MapController', ['$rootScope', '$scope', '$cookies', '$window', '$timeout', '$interval', 'MarkerService', 'LayerService', '$routeParams', function($rootScope, $scope, $cookies, $window, $timeout, $interval, MarkerService, LayerService, $routeParams) {

			$scope.map_id=$routeParams;

			$scope.coordsystem={
				"Map Grid": {
					xscale: 9.445,
					xoffset: 138,
					longoffset: 10,
					yscale: 9.41,
					yoffset: 123,
					latoffset: 10
				},
				"GPS Grid": {
					xscale: 9.17,
					xoffset: 138,
					longoffset: 10,
					yscale: 9.684,
					yoffset: 255,
					latoffset: 24.7
				}
			}

			$scope.currentcoordsystem="GPS Grid";

			$scope.calclat=function(y) {
				return roundto1((y-$scope.coordsystem[$scope.currentcoordsystem].yoffset)/$scope.coordsystem[$scope.currentcoordsystem].yscale+$scope.coordsystem[$scope.currentcoordsystem].latoffset);
			}

			$scope.calcy=function(lat) {
				return (lat-$scope.coordsystem[$scope.currentcoordsystem].latoffset)*$scope.coordsystem[$scope.currentcoordsystem].yscale+$scope.coordsystem[$scope.currentcoordsystem].yoffset;
			}

			$scope.calclong=function(x) {
				return roundto1((x-$scope.coordsystem[$scope.currentcoordsystem].xoffset)/$scope.coordsystem[$scope.currentcoordsystem].xscale+$scope.coordsystem[$scope.currentcoordsystem].longoffset);
			}

			$scope.calcx=function(long) {
				return (long-$scope.coordsystem[$scope.currentcoordsystem].longoffset)*$scope.coordsystem[$scope.currentcoordsystem].xscale+$scope.coordsystem[$scope.currentcoordsystem].xoffset;
			}

			/*$scope.layers=[
				{name: "Enemy", color: "red", visible: true},
				{name: "Friendly", color: "lime", visible: true},
				{name: "Minor Enemy", color: "orange", visible: true},
				{name: "Neutral", color: "yellow", visible: true},
				{name: "Landmark", color: "white", visible: true}
			];*/
			/*$scope.markers={
				'0': {X: '50', Y: '50', R: '3', layer_id: '0', name: "One"},
				'1': {X: '150', Y: '150', R: '3', layer_id: '0', name: "Two"},
				'2': {X: '250', Y: '250', R: '3', layer_id: '0', name: "Three"},
				'3': {X: '350', Y: '350', R: '3', layer_id: '0', name: "Four"},
				'4': {X: '450', Y: '450', R: '3', layer_id: '0', name: "Five"},
				'6': {X: '550', Y: '550', R: '3', layer_id: '1', name: "Six"}
			};

			MarkerService.update($scope.markers);*/

			$scope.tableview=false;
			$scope.showhelp=false;
			$scope.showmousecoords=true;

			$scope.$watch('mapstyle', function(value) {
				$rootScope.mapstyle=value;
				var now=new Date();
				$cookies.putObject('mapstyle', value, {expires: new Date(now.getFullYear(), now.getMonth()+6, now.getDate()), path: '/map'});
			});
			$scope.$watch('mapstyles', function(value) {
				$rootScope.mapstyles=value;
			});

			$scope.mapstyles=[
				{
					name: "Paper",
					mapimage: "map-paper.gif",
					backgroundcolor: "#FFFFFF",
					backgroundimage: "back.jpg"
				},
				{
					name: "Sci-Fi",
					mapimage: "map-scifi.gif",
					backgroundcolor: "#101111",
					backgroundimage: ""
				},
				{
					name: "Satellite",
					mapimage: "map-satellite.jpg",
					backgroundcolor: "#6F73B4",
					backgroundimage: ""
				}
			];
			$scope.mapstyle=$cookies.getObject('mapstyle');
			if (!($scope.mapstyle in $scope.mapstyles)) {
				$scope.mapstyle='0';
			}

			$scope.markers=MarkerService.get();

			$scope.layers=LayerService.get();

			$scope.favmarkers={};
			$scope.favmarkercount=0;

			$scope.markers.$promise.then(function(results) {
				$scope.lastupdate=new Date().getTime()/1000;
				angular.forEach(results, function(result) {
					result.x=$scope.calcx(result.longitude);
					result.y=$scope.calcy(result.latitude);
					if (result.fav=='1') {
						$scope.favmarkers[result.id]=result;
						$scope.favmarkercount++;
					}
				});
			});

			$scope.realtime=0;
			$scope.pendingmarkerupdates=0;
			$scope.pendinglayerupdates=0;
			var realtimeinterval=null;
			var timeouts={};
			var layertimeouts={};

			$scope.viewport={
				zoomfactor: 1,
				mousex: 100,
				mousey: 100,
				left: 0,
				top: 0,
				height: 1000,
				width: 1000,
				SVGHeight: $window.innerHeight,
				SVGWidth: $window.innerHeight,
				contentheight: 1000,
				contentwidth: 1000
			};

			$scope.$watch(function() {
				return $window.innerHeight;
				},
				function(value) {
					$scope.viewport.SVGHeight=value;
					$scope.viewport.SVGWidth=value;
					$scope.viewport.basezoomfactor=$scope.viewport.SVGWidth/$scope.viewport.contentwidth;


				}
			);
			$scope.viewport.zoomfactor=$scope.viewport.SVGWidth/$scope.viewport.contentwidth;
			$scope.viewport.basezoomfactor=$scope.viewport.SVGWidth/$scope.viewport.contentwidth;
			$scope.viewport.magfactor=1;

			$scope.$watch('viewport.zoomfactor', function(newValue, oldValue) {
				$scope.viewport.magfactor=((newValue-$scope.viewport.basezoomfactor)/3+1);
			});

			$scope.markercontrol={
				current: null,
				drag: null
			}

			$scope.layercontrol={
				current: null
			}

			$scope.filtering={
				name: "",
				regexname: false,
				minage: 0,
				filterminage: false,
				maxage: 1,
				filtermaxage: false
			}

			$scope.panning={
				startx: null,
				starty: null,
				finishx: null,
				finishy: null,
				starttop: null,
				startleft: null
			}

			$scope.dragging={
				startx: null,
				starty: null
			}

			$scope.$watch('viewport.SVGHeight', function(newValue, oldValue) {
				$scope.viewport.SVGWidth=newValue;
			});

			$scope.$watch('markercontrol.current.x', function(newValue, oldValue) {
				if ($scope.markercontrol.current) {
					$scope.markercontrol.current.longitude=$scope.calclong(newValue);
				}
			});

			$scope.$watch('markercontrol.current.y', function(newValue, oldValue) {
				if ($scope.markercontrol.current) {
					$scope.markercontrol.current.latitude=$scope.calclat(newValue);
				}
			});

			$scope.addMarker=function(marker) {
				var newmarker=new MarkerService();
				for (var property in marker) {
					if (marker.hasOwnProperty(property)) {
						newmarker[property]=marker[property];
					}
				}
				newmarker.longitude=$scope.calclong(newmarker.x);
				newmarker.latitude=$scope.calclat(newmarker.y);
				newmarker.$create(function success(newmarker) {
					newmarker.x=$scope.calcx(newmarker.longitude);
					newmarker.y=$scope.calcy(newmarker.latitude);
					$scope.markers[newmarker.id]=newmarker;
					$scope.markercontrol.current=$scope.markers[newmarker.id];
				});
			}

			$scope.deleteCurrentMarker=function() {
				if (!$scope.markercontrol.current.confirmdelete==1) {
					alert ("Confirm delete by ticking box");
				} else {
					if ($scope.markercontrol.current.fav=='1') {
						$scope.markercontrol.current.fav='0'; //Emulate checkbox value change
						$scope.toggleFavMarker($scope.markercontrol.current);
					}
					$scope.markers.$delete($scope.markercontrol.current);
					delete $scope.markers[$scope.markercontrol.current.id];
					$scope.markercontrol.current=null;
				}
			}

			$scope.closeCurrentMarker=function() {
				$scope.markercontrol.current=null;
			}

			$scope.debounceSaveMarker=function(marker) {
				marker.updated=Math.floor(new Date().getTime()/1000);
				if (timeouts[marker.id]) {
					$timeout.cancel(timeouts[marker.id]);
				}
				$scope.pendingmarkerupdates++;
				timeouts[marker.id]=$timeout(function() {wrappedmarker=new MarkerService();angular.extend(wrappedmarker, marker);wrappedmarker.$save(wrappedmarker);}, 1000);
				timeouts[marker.id].then(function() {
					$scope.pendingmarkerupdates--;
				},
				function() {
					$scope.pendingmarkerupdates--;
				});
			}

			$scope.toggleFavMarker=function(marker) {
				if (marker.fav=='1') {
					$scope.favmarkers[marker.id]=marker;
					$scope.favmarkercount++;
				} else {
					delete $scope.favmarkers[marker.id];
					$scope.favmarkercount--;
				}
				$scope.debounceSaveMarker(marker);
			}

			$scope.addLayer=function() {
				var newlayer=new LayerService();
				newlayer.name='New Layer';
				newlayer.color='#ffffff';
				newlayer.visible='1';
				newlayer.$create(function success(newlayer) {
					$scope.layers[newlayer.id]=newlayer;
					$scope.layercontrol.current=newlayer;
				});
			}

			$scope.deleteCurrentLayer=function() {
				if (!$scope.layercontrol.current.confirmdelete==1) {
					alert ("Confirm delete by ticking box");
				} else if (!$scope.layercontrol.replacement) {
					alert ("Select a replacement layer for markers using current layer");
				} else {
					angular.forEach($scope.markers, function(marker) {
						if (marker.layer_id==$scope.layercontrol.current.id) {
							marker.layer_id=$scope.layercontrol.replacement.id;
							$scope.debounceSaveMarker(marker);
						}
					});
					$scope.layers.$delete($scope.layercontrol.current);
					delete $scope.layers[$scope.layercontrol.current.id];
					$scope.layercontrol.current=null;
				}
			}

			$scope.closeCurrentLayer=function() {
				$scope.layercontrol.current=null;
			}

			$scope.debounceSaveLayer=function(layer) {
				if (layertimeouts[layer.id]) {
					$timeout.cancel(layertimeouts[layer.id]);
				}
				$scope.pendinglayerupdates++;
				layertimeouts[layer.id]=$timeout(function() {wrappedlayer=new LayerService();angular.extend(wrappedlayer, layer);wrappedlayer.$save(wrappedlayer);}, 1000);
				layertimeouts[layer.id].then(function() {
					$scope.pendinglayerupdates--;
				},
				function() {
					$scope.pendinglayerupdates--;
				});
			}

			window.onbeforeunload=function() {
				if ($scope.pendingmarkerupdates>0 || $scope.pendinglayerupdates>0) {
					return "Pending Updates";
				}
			};

			$scope.markerAgeFilter=function(marker) {
				var now=Date.now() / 1000 | 0;
				return (!$scope.filtering.filtermaxage || marker.updated>now-$scope.filtering.maxage*24*60*60) &&
				(!$scope.filtering.filterminage || marker.updated<now-$scope.filtering.minage*24*60*60);
			};

			$scope.realTimeMode=function() {
				if ($scope.realtime==1) {
					realtimeinterval=$interval(function() {
					MarkerService.poll({time:$scope.lastupdate}, function(results) {
						angular.forEach(results, function(result) {
							if (result.id!=undefined) {
								result.x=$scope.calcx(result.longitude);
								result.y=$scope.calcy(result.latitude);
								if (result.fav=='1' && $scope.favmarkers[result.id]==null) {
									$scope.favmarkers[result.id]=result;
									$scope.favmarkercount++;
								}
								if ($scope.markers[result.id]==undefined || $scope.markers[result.id].updated<result.updated) {
									$scope.markers[result.id]=result;
									if ($scope.markercontrol.current!=null && $scope.markercontrol.current.id==result.id) {
										$scope.markercontrol.current=$scope.markers[result.id];
									}
								}
								$scope.lastupdate=new Date().getTime()/1000;
							}
						});
					});
					}, 1000);
				} else {
					$interval.cancel(realtimeinterval);
				}
			}

		}]);