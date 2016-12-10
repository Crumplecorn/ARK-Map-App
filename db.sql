CREATE TABLE `layers` (
  `map_id` varchar(255) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'New Layer',
  `color` varchar(10) NOT NULL DEFAULT '#ff0000',
  `visible` tinyint(4) NOT NULL DEFAULT '1',
  UNIQUE KEY `layer_key` (`map_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1

CREATE TABLE `markers` (
  `map_id` varchar(255) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'New Marker',
  `longitude` float NOT NULL,
  `latitude` float NOT NULL,
  `r` float NOT NULL,
  `gps` tinyint(1) NOT NULL DEFAULT '0',
  `layer_id` int(11) NOT NULL,
  `fav` tinyint(1) NOT NULL DEFAULT '0',
  `showcoords` tinyint(1) NOT NULL DEFAULT '0',
  `updated` int(11) NOT NULL DEFAULT '0',
  `description` varchar(255) NOT NULL DEFAULT '',
  UNIQUE KEY `marker_key` (`map_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
