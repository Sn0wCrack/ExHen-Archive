// ==UserScript==
// @name       ExHentai Archive
// @match      *://exhentai.org/*
// @match      *://e-hentai.org/*
// @require    https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js
// ==/UserScript==

var baseUrl = '//your.archive.url.com/';
var key = 'changeme';

function createArchiveLink(gid, token) {
    var link = $('<div><a href="#">Send to archive</a></div>');
    link.data('gid', gid);
    link.data('token', token);
    
    link.on('click', function() {
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
            var link = "";
            if (data.data.deleted == 0) {
               link = $('<a href="#" target="_blank">Archived</a>');
            } else if (data.data.deleted >= 1) {
                link = $('<a href="#">Deleted</a>');
            }
            
            if(data.data.archived && data.data.deleted == 0) {
                link.prop('href', baseUrl + '?' + $.param({ action: 'gallery', id: data.data.id }));
            }
            else if (!data.data.archived) {
                link.on('click', function() {
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
    var galleries = $('div.gl1t', container);
    var gids = [ ];
    
    galleries.each(function() {
        var galleryContainer = $(this);
        var link = $('a', galleryContainer).prop('href');
        
        var bits = link.split("/");
        
        var gid = bits[4];
        var token = bits[5];
        
        gids.push(gid);
        
        galleryContainer.data('gid', gid);
        
        $.getJSON(baseUrl + 'api.php', { action: 'hasgallery', gid: gid, key: key }, function(data, result) {
           if (!data.data.exists) {
              var link = createArchiveLink(gid, token);
              link.css({ 'text-align': center, 'font-size': '12px' });
              link.on('click', function() {
                  $(this).parents('gl1t').css({ background: 'green' });
              });
        
              link.appendTo(galleryContainer);
           } else {
             var res = "";
             if (data.data.archived && data.data.deleted == 0) {
                res = $('<div><p>Archived</p></div>');
                galleryContainer.css({background: 'green'});
             }
               
             if (data.data.deleted >= 1) {
               res = $('<div><p>Deleted</p></div>');  
               galleryContainer.css({background: '#AA0000'});
             }
             res.appendTo(galleryContainer);
           }
        });
    });
});
