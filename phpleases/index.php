<?php
	require '../private/phpleases.php';
	$leases = dhcpd_get_leases('/var/lib/dhcp/dhcpd.leases');
	$cache = bind_get_cache('/var/named/named_dump.db');
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>Server-Status: ralph</title>
		<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	</head>
	<body>
		<h1>Server-Status: ralph</h1>
		<h2>DHCP Leases</h2>
		<table border="1">
			<tr>
				<th colspan="4"></th>
				<th colspan="3">Zeiten</th>
				<th></th>
			</tr>
			<tr>
				<th>IP-Adresse</th>
				<th>Hardware-Typ</th>
				<th>MAC-Adresse</th>
				<th>Hostname</th>
				<th>Lease-Beginn</th>
				<th>Lease-Ende</th>
				<th>letzte Transaktion</th>
				<th>Variablen</th>
			</tr>
			<?php foreach ($leases as $ip => $lease) { if ($lease['bindingstate'] == 'active' && !$lease['abandoned']) { ?><tr>
				<td><?php echo $ip; ?></td>
				<td><?php echo $lease['hwtype']; ?></td>
				<td><?php echo $lease['mac']; ?></td>
				<td><?php echo htmlentities($lease['hostname']); ?></td>
				<td><?php echo date('d.m.Y, H:i:s (T)', $lease['start']); ?></td>
				<td><?php echo date('d.m.Y, H:i:s (T)', $lease['end']); ?></td>
				<td><?php echo date('d.m.Y, H:i:s (T)', $lease['lasttransaction']); ?></td>
				<td><?php foreach ($lease['variables'] as $k => $v) { echo htmlentities($k).' = '.htmlentities($v).'<br />'; } ?></td>
			</tr><?php }; } ?>
		</table>
		<h2>BIND Cache</h2>
		Letzter Dump: <?php echo date('d.m.Y, H:i:s (T)', $cache['date']); ?>
		<br />
		Einträge: <?php echo count($cache['entries']); ?>
		<table border="1">
			<tr>
				<th>Domain</th>
				<th>Einträge</th>
			</tr>
			<?php foreach ($cache['entries'] as $domain => $entry) { ?><tr>
				<td><?php echo $domain; ?></td>
				<td><?php foreach ($entry as $type => $values) { foreach ($values as $v) { echo '<strong>'.$type.'</strong>: '.$v[0].' (TTL: '.$v[1].')<br />'; } } ?></td>
			</tr><?php } ?>
		</table>
	</body>
</html>
