// Copyright 2012 © Gavin Kistner, !@phrogz.net
// License: http://phrogz.net/JS/_ReuseLicense.txt

/*******************************************************************
 SVGPanUnscale.js
 Allows selected elements that have been zoomed by SVGPan to keep
 their original size while still being placed correctly.

 Also undoes rotation/skew (any transform other than translation).

 USAGE:  unscaleEach('.non-scaling');
*******************************************************************/

// Undo the scaling to selected elements inside an SVGPan viewport
function unscaleEach(selector){
  if (!selector) selector = "g.non-scaling > *";
  window.addEventListener('mousewheel',     unzoom, false);
  window.addEventListener('DOMMouseScroll', unzoom, false);
  function unzoom(evt){
    // getRoot is a global function exposed by SVGPan
    var r = getRoot(evt.target.ownerDocument);
    [].forEach.call(r.querySelectorAll(selector), unscale);
  }
}

// Counteracts all transforms applied above an element.
// Apply a translation to the element to have it remain at a local position
function unscale(el){
  var svg = el.ownerSVGElement;
  var xf = el.scaleIndependentXForm;
  if (!xf){
    // Keep a single transform matrix in the stack for fighting transformations
    xf = el.scaleIndependentXForm = svg.createSVGTransform();
    // Be sure to apply this transform after existing transforms (translate)
    el.transform.baseVal.appendItem(xf);
  }
  var m = svg.getTransformToElement(el.parentNode);
  m.e = m.f = 0; // Ignore (preserve) any translations done up to this point
  xf.setMatrix(m);
}
