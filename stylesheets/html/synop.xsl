<?xml version='1.0'?>
<!DOCTYPE xsl:stylesheet [
<!ENTITY RE "&#10;">
<!ENTITY nbsp "&#160;">
]>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                version='1.0'>

<!-- ********************************************************************
     $Id: synop.xsl,v 1.3 2003-06-10 11:42:20 sfox Exp $
     ********************************************************************

     This file is part of the XSL DocBook Stylesheet distribution.
     See ../README or http://nwalsh.com/docbook/xsl/ for copyright
     and other information.

     ******************************************************************** -->

<!-- ==================================================================== -->

<!-- synopsis is in verbatim -->

<xsl:template match="funcsynopsis">
  <xsl:call-template name="informal.object"/>
</xsl:template>

<xsl:template match="funcprototype">
 <span dir="ltr">
  <xsl:apply-templates mode="func.prototype"/>
 </span>
 </xsl:template>

 <xsl:template match="funcdef" mode="func.prototype">
  <xsl:apply-templates/>
 </xsl:template>

 <xsl:template match="paramdef" mode="func.prototype">
  <xsl:variable name="optional" select="count(./parameter/optional)"/>
  <xsl:variable name="paramnum">
   <xsl:number count="paramdef" format="1"/>
  </xsl:variable>
  <xsl:if test="$paramnum=1">(</xsl:if>
  <xsl:if test="$optional&gt;0">[</xsl:if>
  <xsl:choose>
   <xsl:when test="$funcsynopsis.style='ansi'">
    <xsl:apply-templates/>
   </xsl:when>
   <xsl:otherwise>
    <xsl:apply-templates/>
   </xsl:otherwise>
  </xsl:choose>
  <xsl:choose>
   <xsl:when test="following-sibling::paramdef">
    <xsl:text>, </xsl:text>
   </xsl:when>
   <xsl:otherwise>
   <xsl:variable name="optionals" select=".//optional|preceding-sibling::paramdef/*/optional"/>
    <xsl:for-each select="$optionals">]</xsl:for-each>
    <xsl:text>);</xsl:text> 
   </xsl:otherwise>
  </xsl:choose>
 </xsl:template>

 <xsl:template match="optional">
  <xsl:apply-templates />
 </xsl:template>

 <xsl:template match="desc|shortdesc" mode="func.prototype">
  <xsl:apply-templates />
 </xsl:template>

  <xsl:template match="shortdesc" mode="synoptic.mode">
  <xsl:apply-templates />
 </xsl:template>

 <xsl:template match="desc|shortdesc">
  <xsl:apply-templates />
 </xsl:template>

 
 <xsl:template match="funcprototype/paramdef"/>
 <xsl:template match="funcprototype/funcdef"/>

</xsl:stylesheet>
