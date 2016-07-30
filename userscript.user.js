// ==UserScript==
// @name       ExHentai archive
// @match      *://exhentai.org/*
// @match      *://g.e-hentai.org/*
// @require    http://ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js
// ==/UserScript==

var baseUrl = '//your.archive.url.com/';
var key = 'changeme';

function createArchiveLink(gid, token) {
    var link = $('<a href="#">Send to archive</a>');
    link.data('gid', gid);
    link.data('token', token);
    
    link.click(function() {
        $.getJSON(baseUrl + 'api.php', { action: 'addgallery', gid: link.data('gid'), token: link.data('token'), key: key }, function(data, result) {
            if(data.ret === true && result === 'success') {
                $(link).css({
                    color: '#777',
                    pointerEvents: 'none'
                });
            }
            else {
                alert('An error occured while adding to archive');
            }
        });
        
        return false;
    });
    
    return link;
}

$('div#gd5').each(function() { //archive button on gallery detail
    var container = $(this);
    
    $.getJSON(baseUrl + 'api.php', { action: 'hasgallery', gid: gid, key: key }, function(data, result) {
        if(data.data.exists) {
            var p = $('<p class="g2"><img src="//exhentai.org/img/mr.gif"> </p>');
            var link = $('<a href="#" target="_blank">Archived</a>');
            
            if(data.data.archived) {
                link.prop('href', baseUrl + '?' + $.param({ action: 'gallery', id: data.data.id }));
            }
            else {
                link.click(function() {
                    alert('Not yet downloaded');
                    return false;
                });
            }
            
            link.appendTo(p);
            $('.g2', container).last().after(p);
        }
        else {
            var p = $('<p class="g2"><img src="//exhentai.org/img/mr.gif"> </p>');
            var link = createArchiveLink(gid, token);
            link.appendTo(p);
            
            $('.g2', container).last().after(p);
        }
    });
});

$('div.itg').each(function() { //gallery search
    var container = $(this);
    var galleries = $('div.id1', container);
    var gids = [ ];
    
    galleries.each(function() {
        var galleryContainer = $(this);
        var link = $('div.id2 a', galleryContainer).prop('href');
        
        var bits = link.split("/");
        
        var gid = bits[4];
        var token = bits[5];
        
        gids.push(gid);
        
        galleryContainer.data('gid', gid);
        
        var link = createArchiveLink(gid, token);
        link.css({ fontSize: '9px' });
        link.click(function() {
            $(this).parents('.id1').css({ background: 'green' });
        });
        
        link.prependTo($('.id44', galleryContainer));
    });
    
    $.getJSON(baseUrl + 'api.php', { action: 'hasgalleries', gids: gids, key: key }, function(ret) {
        if(ret.ret) {
            for(var i in ret.data) {
                var row = ret.data[i];
                
                galleries.filter(function() {
                    return $(this).data('gid') == row.exhenid;
                }).css({ background: 'green' });
            }
        }
    });
});
