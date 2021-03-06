jQuery(document).ready(function () {
    
    getPreference();
    jQuery("#community-nav-collapse-toggle").on("click", function (e) {
        setPreference();
        e.preventDefault();
        if (jQuery("#right-community-nav").hasClass("hide")) {
            expandMenu ();
        } else {
            collapseMenu ();
        }
    });
});

function getPreference () {
    var preference = readCookie("community_"+ COMMUNITY_ID +"_nav_preference");
    if (preference == "collapsed") {
        collapseMenu ();
    } else {
        expandMenu();
    }
}

function setPreference () {
    var preference = (jQuery("#right-community-nav").hasClass("hide") ? "expanded" : "collapsed");
    createCookie("community_"+ COMMUNITY_ID +"_nav_preference", preference, 365);
}

function collapseMenu () {
    jQuery("#community-nav-collapse-toggle").removeClass("active");
    jQuery(".content-area").removeClass("span6-5").addClass("span9-5");
    jQuery("#right-community-nav").addClass("hide").removeClass("span3");
    jQuery("#community-nav-menu-icon").removeClass("active");
}

function expandMenu () {
    jQuery("#community-nav-collapse-toggle").addClass("active");
    jQuery("#community-nav-menu-icon").addClass("active");
    jQuery("#right-community-nav").addClass("span3").removeClass("hide");
    jQuery(".content-area").removeClass("span9-5").addClass("span6-5");
} 

