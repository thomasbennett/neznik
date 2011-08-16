<?php

function wds_seomoz_dashboard_widget () {
	global $wds_options;

	if( !isset($wds_options['access-id']) || !isset($wds_options['secret-key']) ) {
		_e('<p>SEOmoz credentials not properly set up.</p>');
		return;
	}

	$target_url = preg_replace('!http(s)?:\/\/!', '', get_bloginfo('url'));
	$seomozapi = new SEOMozAPI( $wds_options['access-id'], $wds_options['secret-key'] );
	$urlmetrics = $seomozapi->urlmetrics( $target_url );

	$attribution = str_replace( '/', '%252F', untrailingslashit( $target_url ) );
	$attribution = "http://www.opensiteexplorer.org/$attribution/a";

	if (!is_object($urlmetrics)) {
		printf( __('Unable to retrieve data from the SEOmoz API. Error: %s.' , 'wds'), $urlmetrics );
		return;
	}

	echo '<h4>' . __( 'Domain Metrics' , 'wds') . ' (' . $target_url . ')</h4>
<table class="widefat">
	<thead>
		<tr>
			<th width="75%">' . __( 'Metric' , 'wds') . '</th>
			<th>' . __( 'Value' , 'wds') . '</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th>' . __( 'Metric' , 'wds') . '</th>
			<th>' . __( 'Value' , 'wds') . '</th>
		</tr>
	</tfoot>
	<tbody>
		<tr>
			<td><b>' . __( 'Domain mozRank' , 'wds') . '</b><br />Measure of the mozRank <a href="http://www.opensiteexplorer.org/About#faq_5" target="_blank">(?)</a> of the domain in the Linkscape index</td>
			<td>' . sprintf( __( '10-point score: %s' , 'wds'), "<a href='$attribution'>$urlmetrics->fmrp</a>" ) . '<br />' . sprintf( __( 'Raw score: %s' , 'wds'), "<a href='$attribution' target='_blank'>$urlmetrics->fmrr</a>" ) . '
			</td>
		</tr>
		<tr class="alt">
			<td><b>' . __( 'Domain Authority' , 'wds') . '</b> <a href="http://apiwiki.seomoz.org/w/page/20902104/Domain-Authority/" target="_blank">(?)</a></td>
			<td><a href="' . $attribution . '" target="_blank">' .$urlmetrics->pda . '</a></td>
		</tr>
		<tr>
			<td><b>' . __( 'External Links to Homepage' , 'wds') . '</b><br />The number of external (from other subdomains), juice passing links <a href="http://apiwiki.seomoz.org/w/page/13991139/Juice-Passing" target="_blank">(?)</a> to the target URL in the Linkscape index </td>
			<td><a href="' . $attribution . '" target="_blank">' .$urlmetrics->ueid . '</a></td>
		</tr>
		<tr>
			<td><b>' . __( 'Links to Homepage' , 'wds') . '</b><br />The number of internal and external, juice and non-juice passing links <a href="http://apiwiki.seomoz.org/w/page/13991139/Juice-Passing" target="_blank">(?)</a> to the target URL in the Linkscape index</td>
			<td><a href="' . $attribution . '" target="_blank">' .$urlmetrics->uid . '</a></td>
		</tr>
		<tr>
			<td><b>' . __( 'Homepage mozRank' , 'wds') . '</b><br />Measure of the mozRank <a href="http://www.opensiteexplorer.org/About#faq_5" target="_blank">(?)</a> of the homepage URL in the Linkscape index</td>
			<td>' . sprintf( __( '10-point score: %s' , 'wds'), "<a href='$attribution'>$urlmetrics->umrp</a>" ) . '<br />' . sprintf( __( 'Raw score: %s' , 'wds'), "<a href='$attribution' target='_blank'>$urlmetrics->umrr</a>" ) . '</td>
		</tr>
		<tr>
			<td><b>' . __( 'Homepage Authority' , 'wds') . '</b> <a href="http://apiwiki.seomoz.org/Page-Authority" target="_blank">(?)</a></td>
			<td><a href="' . $attribution . '" target="_blank">' .$urlmetrics->upa . '</a></td>
		</tr>
	</tbody>
</table>
<p>' . __( 'For posts / pages specific metrics refer to the SEOmoz URL metrics module on the Edit Post / Page screen' , 'wds') . '</p>' .
'<p><a href="http://seomoz.org/" target="_blank"><img src="' . WDS_PLUGIN_URL . 'images/linkscape-logo.png" title="SEOmoz Linkscape API" /></a></p>';
}

function wds_add_seomoz_dashboard_widget () {
	wp_add_dashboard_widget('wds_seomoz_dashboard_widget', 'SEOmoz', 'wds_seomoz_dashboard_widget');
}
add_action('wp_dashboard_setup', 'wds_add_seomoz_dashboard_widget' );