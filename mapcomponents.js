function roundto1(num) {
	return Math.round((num + 0.00001) * 10) / 10;
}

var marker = angular.module('marker', []);

		marker.filter('xtolong', function() {
			return function(X, xoffset, xscale, longoffset) {
				return roundto1((X-xoffset)/xscale+longoffset);
			}
		});

		marker.filter('ytolat', function() {
			return function(Y, yoffset, yscale, latoffset) {
				return roundto1((Y-yoffset)/yscale+latoffset);
			}
		});

		marker.directive('latitude', function() {
			return {
				require: 'ngModel',
				link: function(scope, element, attrs, ngModel) {
					function fromY(Y) {
						return roundto1((Y-scope.coordsystem[scope.currentcoordsystem].yoffset)/scope.coordsystem[scope.currentcoordsystem].yscale+scope.coordsystem[scope.currentcoordsystem].latoffset);
					}
					function toY(lat) {
						return (lat-scope.coordsystem[scope.currentcoordsystem].latoffset)*scope.coordsystem[scope.currentcoordsystem].yscale+scope.coordsystem[scope.currentcoordsystem].yoffset;
					}
					ngModel.$parsers.push(toY);
					ngModel.$formatters.push(fromY);
				}
			}
		});

		marker.directive('longitude', function() {
			return {
				require: 'ngModel',
				link: function(scope, element, attrs, ngModel) {
					function fromX(X) {
						return roundto1((X-scope.coordsystem[scope.currentcoordsystem].xoffset)/scope.coordsystem[scope.currentcoordsystem].xscale+scope.coordsystem[scope.currentcoordsystem].longoffset);
					}
					function toX(long) {
						return (long-scope.coordsystem[scope.currentcoordsystem].longoffset)*scope.coordsystem[scope.currentcoordsystem].xscale+scope.coordsystem[scope.currentcoordsystem].xoffset;
					}
					ngModel.$parsers.push(toX);
					ngModel.$formatters.push(fromX);
				}
			}
		});

		marker.directive('marker', function() {
			return {
				link: function(scope, element, attrs) {

					element.bind('mousemove', function(event) {
						event.preventDefault();
					});

					element.bind('mousedown', function(event) {
						event.preventDefault();
						scope.$apply(function () {
							scope.dragging.startx=scope.viewport.mousex;
							scope.dragging.starty=scope.viewport.mousey;
							scope.markercontrol.drag=scope.markercontrol.current=scope.markers[element.attr('markerindex')];
							scope.markercontrol.drag.id=scope.markercontrol.current.id=element.attr('markerindex');
						});
					});

					element.bind('mouseup', function(event) {
						event.preventDefault();
						scope.$apply(function () {
							scope.markercontrol.drag=null;
						});
					});

					element.bind('contextmenu', function(event) {
						event.preventDefault();
					})
				}
			}
		});

		marker.directive('markertext', function() {
			return {
				link: function(scope, element, attrs) {

					element.bind('mousemove', function(event) {
						event.preventDefault();
					});

					element.bind('mousedown', function(event) {
						event.preventDefault();
					});

					element.bind('mouseup', function(event) {
						event.preventDefault();
					});
				}
			}
		});

		marker.directive('pannable', function() {
			return {
				link: function(scope, element, attrs) {
					element.bind('mousedown', function(event) {
						event.preventDefault();
						scope.$apply(function () {
							panning=scope.panning;
							viewport=scope.viewport;
							panning.startx=event.clientX;
							panning.starty=event.clientY;
							panning.startleft=viewport.left;
							panning.starttop=viewport.top;
						});

						element.bind('mousemove', function(event) {
							event.preventDefault();
							scope.$apply(function () {
								panning=scope.panning;
								viewport=scope.viewport;
								panning.finishx=event.clientX;
								panning.finishy=event.clientY;
								viewport.left=Math.max(0, panning.startleft+(panning.startx-panning.finishx)/viewport.zoomfactor);
								viewport.top=Math.max(0, panning.starttop+(panning.starty-panning.finishy)/viewport.zoomfactor);
								viewport.left=Math.min(viewport.left, viewport.contentwidth-viewport.width)
								viewport.top=Math.min(viewport.top, viewport.contentheight-viewport.height)
							});
						});

					});

					element.bind('mouseup', function(event) {
						event.preventDefault();
						element.unbind('mousemove');
						scope.$apply(function () {
							scope.markercontrol.drag=null;
						});
					});
				}
			}
		});

	var scrollLogic=function (event, scope) {
							event=window.event || event;
							var delta=-event.wheelDelta/5 || event.detail*2;

							var viewport=scope.viewport;

							//Current mouse location distance from edges
							var xOffset=(viewport.mousex-viewport.left)*viewport.zoomfactor;
							var yOffset=(viewport.mousey-viewport.top)*viewport.zoomfactor;

							//Zoom
							viewport.zoomfactor=Math.min(10, Math.max(viewport.SVGWidth/viewport.contentwidth, viewport.zoomfactor-delta/100*viewport.zoomfactor));
							viewport.width=viewport.SVGWidth/viewport.zoomfactor;
							viewport.height=viewport.SVGHeight/viewport.zoomfactor;

							//Offset
							viewport.left=Math.max(0, viewport.mousex-xOffset/viewport.zoomfactor);
							viewport.top=Math.max(0,viewport.mousey-yOffset/viewport.zoomfactor);
							viewport.left=Math.min(viewport.left, viewport.contentwidth-viewport.width);
							viewport.top=Math.min(viewport.top, viewport.contentheight-viewport.height);
						}

		marker.directive('zoomablesvg', function() {
			return {
				link: function(scope, element, attrs) {
					element.bind('DOMMouseScroll', function(event) {
						event.preventDefault();
						scope.$apply(scrollLogic(event, scope));
					});

					element.bind('mousewheel', function(event) {
						event.preventDefault();
						scope.$apply(scrollLogic(event, scope));
					});

					element.bind('mousemove', function(event) {
						scope.$apply(function () {
							//Mouse Position Calculation
							el=element[0];
							var pt=el.createSVGPoint();
							pt.x=event.clientX;
							pt.y=event.clientY;
							loc=pt.matrixTransform(el.getScreenCTM().inverse());
							scope.viewport.mousex=loc.x;
							scope.viewport.mousey=loc.y;

							//Marker Dragging
							if (scope.markercontrol.drag!=null && Math.abs(scope.dragging.startx-scope.viewport.mousex)+Math.abs(scope.dragging.starty-scope.viewport.mousey)>5/scope.viewport.zoomfactor) {
								scope.dragging.startx=null;
								scope.dragging.starty=null;
								scope.markercontrol.drag.x=scope.viewport.mousex;
								scope.markercontrol.drag.y=scope.viewport.mousey;
								scope.debounceSaveMarker(scope.markercontrol.drag);
							}
						});
					});

					element.bind('dblclick', function(event) {
						scope.$apply(function(MarkerService) {
							newmarker={
								x: scope.viewport.mousex,
								y: scope.viewport.mousey,
								r: '3',
								layer_id: 0,
								name: ""
							}
							scope.addMarker(newmarker);
							window.document.getElementById('currentmarkername').focus();
						});
					});

				}
			}
		});