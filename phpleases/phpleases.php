<?php
	define('DEBUG', false);
	function dhcpd_get_leases ($file)
	{
		// Calculate utc-localtime offset:
		$_offset = date('Z');
		
		$leases = array();
		$key = null;

		$f = fopen($file, 'r');
		while (($line = fgets($f)) !== false)
		{
			$line = trim($line);
			if ($line && strlen($line) != 0 && $line[0] != '#')
			{
				if (substr($line, 0, 6) == 'lease ')
				{
					$key = substr($line, 6, strlen($line)-8);
					$leases[$key] = array(
						'hwtype' => null,
						'mac' => null,
						'hostname' => null,
						'start' => 0,
						'end' => 0,
						'lasttransaction' => 0,
						'abandoned' => false,
						'bindingstate' => null,
						'nextbindingstate' => null,
						'rewindbindingstate' => null,
						'variables' => array()
					);
					$invalid = false;
				}
				else if ($line == '}')
				{
					$key = null;
				}
				else
				{
					if ($key != null && !$invalid)
					{
						if (substr($line, 0, 7) == 'starts ')
						{
							// Lease start time
							$start = strtotime(substr($line, 9, strlen($line)-10))+$_offset;
							if ($leases[$key]['start'] > $start)
							{
								$invalid = true;
								continue;
							};
							$leases[$key]['start'] = $start;
						}
						else if (substr($line, 0, 5) == 'ends ')
						{
							// Lease end time
							$leases[$key]['end'] = strtotime(substr($line, 7, strlen($line)-8))+$_offset;
						}
						else if (substr($line, 0, 5) == 'cltt ')
						{
							// Last transaction time
							$leases[$key]['lasttransaction'] = strtotime(substr($line, 7, strlen($line)-8))+$_offset;
						}
						else if (substr($line, 0, 9) == 'hardware ')
						{
							// Hardware type & MAC address
							$x = explode(' ', substr($line, 9, strlen($line)-10));
							$leases[$key]['hwtype'] = $x[0];
							$leases[$key]['mac'] = $x[1];
						}
						else if (substr($line, 0, 4) == 'uid ')
						{
							// TODO: UID
						}
						else if (substr($line, 0, 17) == 'client-hostname "')
						{
							// Hostname
							$leases[$key]['hostname'] = substr($line, 17, strlen($line)-19);
						}
						else if (substr($line, 0, 10) == 'abandoned;')
						{
							// Abandoned
							$leases[$key]['abandoned'] = true;
						}
						else if (substr($line, 0, 14) == 'binding state ')
						{
							// Binding state
							$leases[$key]['bindingstate'] = substr($line, 14, strlen($line)-15);
						}
						else if (substr($line, 0, 19) == 'next binding state ')
						{
							// Next binding state
							$leases[$key]['nextbindingstate'] = substr($line, 19, strlen($line)-20);
						}
						else if (substr($line, 0, 21) == 'rewind binding state ')
						{
							// Next binding state
							$leases[$key]['rewindbindingstate'] = substr($line, 21, strlen($line)-22);
						}
						// TODO: option agent.circuit-id string;
						// TODO: option agent.remote-id string;
						else if (substr($line, 0, 4) == 'set ')
						{
							$x = explode('=', substr($line, 4, strlen($line)-5));
							$k = trim($x[0]);
							$v = trim(trim($x[1]), '"');
							$leases[$key]['variables'][$k] = $v;
						}
						else if (DEBUG)
						{
							// Unknown line
							echo $key.': '.$line."\n";
						};
					};
				};
			};
		}
		return $leases;
	}
	// Run: rndc dumpdb -cache
	function bind_get_cache ($file)
	{
		$result = array(
			'date' => 0,
			'entries' => array()
		);
		$domain = null;
		$ttloffset = 0;
		
		$f = fopen($file, 'r');
		while (($line = fgets($f)) !== false)
		{
			$line = trim($line);
			if ($line && strlen($line) != 0 && $line[0] != ';')
			{
				$el = preg_split("/(\t| )/", $line);
				switch (count($el))
				{
					case 2:
						if ($el[0] == '$DATE')
						{
							$result['date'] = mktime(
								substr($el[1], 8,2),
								substr($el[1],10,2),
								substr($el[1],12,2),
								substr($el[1], 4,2),
								substr($el[1], 6,2),
								substr($el[1], 0,4)
							)+date('Z');
							$ttloffset = time()-$result['date'];
						};
						break;
					case 3:
						// TTL, Type, Entry
						if ($domain != null)
						{
							$ttl = intval($el[0])-$ttloffset;
							$type = $el[1];
							$entry = trim($el[2], '.');
							
							if (!array_key_exists($type, $result['entries'][$domain]))
								$result['entries'][$domain][$type] = array();
							$result['entries'][$domain][$type][] = array($entry, $ttl);
						};
						break;
					case 4:
						// Domain, TTL, Type, Entry
						$domain = trim($el[0], '.');
						$ttl = intval($el[1])-$ttloffset;
						$type = $el[2];
						$entry = trim($el[3], '.');
						
						if (array_key_exists($domain, $result['entries']))
						{
							if (!array_key_exists($type, $result['entries'][$domain]))
								$result['entries'][$domain][$type] = array();
							$result['entries'][$domain][$type][] = array($entry, $ttl);
						}
						else
						{
							$result['entries'][$domain] = array(
								$type => array(
									array($entry, $ttl)
								)
							);
						};
						break;
					case 5:
						if (strlen($el[1]) == 0)
						{
							// Domain, "", TTL, Type, Entry
							$domain = trim($el[0], '.');
							$ttl = intval($el[2])-$ttloffset;
							$type = $el[3];
							$entry = trim($el[4], '.');
						}
						else
						{
							// Domain, TTL, "IN", Type, Entry
							$domain = trim($el[0], '.');
							$ttl = intval($el[1])-$ttloffset;
							$type = $el[3];
							$entry = trim($el[4], '.');
						};
						
						if (array_key_exists($domain, $result['entries']))
						{
							if (!array_key_exists($type, $result['entries'][$domain]))
								$result['entries'][$domain][$type] = array();
							$result['entries'][$domain][$type][] = array($entry, $ttl);
						}
						else
						{
							$result['entries'][$domain] = array(
								$type => array(
									array($entry, $ttl)
								)
							);
						};
						break;
					default:
						
				}
			};
		}
		return $result;
	}
