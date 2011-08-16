<?xml version="1.0" encoding="UTF-8"?>
<!-- Original code inspired by WordPress SEO by Joost de Valk (http://yoast.com/wordpress/seo/) -->
<xsl:stylesheet version="2.0"
        xmlns:html="http://www.w3.org/TR/REC-html40"
        xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:n="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>News Sitemap</title>
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
					<h1>News Sitemap</h1>
					<table id="sitemap" cellpadding="3">
						<caption>This sitemap contains <strong><xsl:value-of select="count(s:urlset/s:url)"/></strong> URLs.</caption>
						<thead>
							<tr>
								<th width="50%" valign="bottom">Title</th>
								<th width="25%" valign="bottom">Keyword(s)</th>
								<th width="10%" valign="bottom">Genre(s)</th>
								<th width="15%" valign="bottom">Publication Date</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th width="50%" valign="top">Title</th>
								<th width="25%" valign="top">Keyword(s)</th>
								<th width="10%" valign="top">Genre(s)</th>
								<th width="15%" valign="top">Publication Date</th>
							</tr>
						</tfoot>
						<tbody>
							<xsl:for-each select="s:urlset/s:url">
								<xsl:variable name="css-class">
									<xsl:choose>
										<xsl:when test="position() mod 2 = 0">even</xsl:when>
										<xsl:otherwise>odd</xsl:otherwise>
									</xsl:choose>
								</xsl:variable>
								<tr class="{$css-class}">
									<td>
										<xsl:variable name="itemURL">
											<xsl:value-of select="s:loc"/>
										</xsl:variable>
										<a href="{$itemURL}">
											<xsl:value-of select="n:news/n:title"/>
										</a>
									</td>
									<td>
										<xsl:value-of select="n:news/n:keywords"/>
									</td>
									<td>
										<xsl:value-of select="n:news/n:genres"/>
									</td>
									<td>
										<xsl:value-of select="concat(substring(n:news/n:publication_date,0,11),concat(' ', substring(n:news/n:publication_date,12,5)))"/>
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>
					<p>
						<em>
						This is a News Sitemap, meant for consumption by Google News (visit <a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&amp;answer=74288">this Google Help article</a> for more info).
						</em>
					</p>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
