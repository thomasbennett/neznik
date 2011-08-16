<?php

require_once ( WDS_PLUGIN_DIR . 'wds-seomoz/class-seomozapi.php' );

/* Add settings page */
function wds_seomoz_settings() {
	global $wds_options;

	$name = 'wds_seomoz';
	$title = 'SEOmoz';
	$description = __( '<p>We make it easy to integrate with SEOmoz - the industry leader in SEO reports.</p>
	<p><a href="http://seomoz.org/api/" target="_blank">Sign-up for a free account</a> to gain access to reports that will tell you how your site stacks up against the competition with all of the important SEO measurement tools - ranking, links, and much more.</p>' , 'wds');

	$fields = array(
		'authentication' => array(
			'title' => __( 'Authentication' , 'wds'),
			'intro' => '',
			'options' => array(
				array(
					'type' => 'text',
					'name' => 'access-id',
					'title' => __( 'Access ID' , 'wds'),
					'description' => ''
				),
				array(
					'type' => 'text',
					'name' => 'secret-key',
					'title' => __( 'Secret Key' , 'wds'),
					'description' => ''
				)
			)
		)
	);

	$contextual_help = '';

	$target_url = str_replace( 'http://', '', get_bloginfo( 'url' ) );

	//if( $pagenow = 'wds_seomoz' && isset( $_GET['updated'] ) ) { // <-- This is the way it was before. It doesn't really work.
	if( wds_is_wizard_step( '4' ) && isset( $_GET['settings-updated'] ) ) { // Changed how we determine settings being saved
		delete_transient( "seomoz_urlmetrics_$target_url" );
	}

	$additional = '';
	if( isset( $wds_options['access-id'] ) && isset( $wds_options['secret-key'] ) ) {

		$seomozapi = new SEOMozAPI( $wds_options['access-id'], $wds_options['secret-key'] );
		$urlmetrics = $seomozapi->urlmetrics( $target_url );

		$attribution = str_replace( '/', '%252F', untrailingslashit( $target_url ) );
		$attribution = "http://www.opensiteexplorer.org/$attribution/a";

		$additional = is_object( $urlmetrics ) ? '
<h3>' . __( 'Domain Metrics' , 'wds') . '</h3>
<table class="widefat" style="width:500px">
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
<p>' . __( 'For posts / pages specific metrics refer to the SEOmoz URL metrics module on the Edit Post / Page screen,' , 'wds') . '</p>
' : '<p>' . sprintf( __( 'Unable to retrieve data from the SEOmoz API. Error: %s.' , 'wds'), $urlmetrics ) . '</p>';

	}

	$additional .= '<p><a href="http://seomoz.org/" target="_blank"><img src="' . WDS_PLUGIN_URL . 'images/linkscape-logo.png" title="SEOmoz Linkscape API" /></a></p>';

	if ( wds_is_wizard_step( '4' ) )
		$settings = new WDS_Core_Admin_Tab( $name, $title, $description, $fields, 'wds', $contextual_help, $additional );
}
add_action( 'init', 'wds_seomoz_settings' );

/* Default settings */
function wds_seomoz_defaults() {
}
add_action( 'init', 'wds_seomoz_defaults' );
