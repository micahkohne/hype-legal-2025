/**
 * Image Module CP Controls Javascript
 * ===================================
 * Animate the display of quality slider
 * 
 * CHANGELOG
 * 
 * 13/12/2021: 1.1.x    First release.
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */
document.addEventListener("DOMContentLoaded",(function(){let e=document.getElementById("img_cp_jpg_default_quality");e&&(e.oninput=function(e){document.querySelector("div[name='jcogs_dqs']").innerHTML=0|e.target.value})}),!1),document.addEventListener("DOMContentLoaded",(function(){let e=document.getElementById("img_cp_jpg_default_png_quality");e&&(e.oninput=function(e){document.querySelector("div[name='jcogs_dpqs']").innerHTML=0|e.target.value})}),!1);