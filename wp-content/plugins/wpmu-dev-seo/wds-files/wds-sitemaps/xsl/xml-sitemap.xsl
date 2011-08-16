<?xml version="1.0" encoding="UTF-8"?>
<!-- Original code inspired by WordPress SEO by Joost de Valk (http://yoast.com/wordpress/seo/) -->
<xsl:stylesheet version="2.0"
        xmlns:html="http://www.w3.org/TR/REC-html40"
				xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>Sitemap</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					body {
						font-family: Helvetica, Arial, sans-serif;
						font-size: 13px;
						color: #666;
					}
					h1 {
						color: #e0e0e0;
					}
					table {
						border: 1px solid #e0e0e0;
						border-collapse: collapse;
						margin-bottom: 1em;
					}
					caption {
						text-align: right;
					}
					#sitemap tr.odd {
						background-color: #eee;
					}
					#sitemap tbody tr:hover {
						background-color: #ccc;
					}
					#sitemap tbody tr:hover td, #sitemap tbody tr:hover td a {
						color: #000;
					}
					#content {
						width: 90%;
						margin: 0 auto;
					}
					p {
						text-align: center;
						color: #333;
						font-size: 11px;
					}
					p a {
						color: #6655aa;
						font-weight: bold;
					}
					a {
						color: #000;
						text-decoration: none;
					}
					a:visited {
						color: #939;
					}
					a:hover {
						text-decoration: underline;
					}
					td, th {
						text-align: left;
						font-size: 11px;
						padding: 5px;
					}
					th {
						background-color: #eeF;
						border-right: 1px solid #e0e0e0;
					}
					thead th {
						border-bottom: 2px solid #ddd;
					}
					tfoot th {
						border-top: 2px solid #ddd;
					}
				</style>
			</head>
			<body>
				<div id="content">
					<h1>Sitemap</h1>
					<table id="sitemap">
						<caption>This sitemap contains <strong><xsl:value-of select="count(sitemap:urlset/sitemap:url)"/></strong> URLs.</caption>
						<thead>
							<tr>
								<th width="75%" valign="bottom">URL</th>
								<th width="5%" valign="bottom">Priority</th>
								<th width="5%" valign="bottom">Images</th>
								<th width="5%" valign="bottom">Change Freq.</th>
								<th width="10%" valign="bottom">Last Change</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th width="75%" valign="top">URL</th>
								<th width="5%" valign="top">Priority</th>
								<th width="5%" valign="top">Images</th>
								<th width="5%" valign="top">Change Freq.</th>
								<th width="10%" valign="top">Last Change</th>
							</tr>
						</tfoot>
						<tbody>
							<xsl:for-each select="sitemap:urlset/sitemap:url">
								<xsl:variable name="css-class">
									<xsl:choose>
										<xsl:when test="position() mod 2 = 0">even</xsl:when>
										<xsl:otherwise>odd</xsl:otherwise>
									</xsl:choose>
								</xsl:variable>
								<tr class="{$css-class}">
									<td>
										<xsl:variable name="item_url">
											<xsl:value-of select="sitemap:loc"/>
										</xsl:variable>
										<a href="{$item_url}">
											<xsl:value-of select="sitemap:loc"/>
										</a>
									</td>
									<td>
										<xsl:value-of select="concat(sitemap:priority*100,'%')"/>
									</td>
									<td>
										<xsl:value-of select="count(image:image)"/>
									</td>
									<td>
										<xsl:value-of select="sitemap:changefreq"/>
									</td>
									<td>
										<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)))"/>
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>
					<p>
						<em>This is an XML Sitemap, meant for consumption by search engines (visit
						 <a href="http://sitemaps.org">sitemaps.org</a> for more info).</em>
					</p>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
