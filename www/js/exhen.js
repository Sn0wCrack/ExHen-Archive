$(document).ready(function() {
	function api(action, params, callback) {
		params = $.extend(params, { action: action });

		var ret = $.getJSON('api.php', params, function(resp) {
			if(!resp.ret) {
				alert('API error: ' + resp.message);
			}
			else {
				if($.isFunction(callback)) {
					callback(resp.data);
				}
			}
		});

		return ret;
	}

	function renderTags(container, tagGroups) {
		$('.tag', container).remove();

		for(var ns in tagGroups) {
			var tags = tagGroups[ns];

			for(var x in tags) {
				var tag = tags[x];

				var tagItem = $('<a/>');

                var tagSearch = escapeTag(ns + ':' + tag);
                var url = '?' + $.param({ search: tagSearch });
                tagItem.prop('href', url);

				tagItem.text(tag);
				tagItem.addClass('tag tag-' + ns);
				tagItem.data('tag', tag);
				tagItem.data('ns', ns);
				tagItem.prop('title', ns + ':' + tag);
				container.prepend(tagItem);
			}
		}
	}

    function escapeTag(tag) {
        if(tag.indexOf(' ') >= 0) {
            return '"' + tag + '"'
        }
        else {
            return tag;
        }
    }

	$('.gallery-list').each(function() {
		var galleryList = $(this);
		var page = 0;
		var topPage = 0;
		var loading = false;
		var search = null;
		var xhr = null;
		var loadersBottom = $('.loaders-bottom');
		var loadersTop = $('.loaders-top');
		var searchCount = $('.search-count');
		var searchForm = $('.search-form');
		var order = 'posted';
		var init = false;
		var end = false;
		var randomSeed = 0;
        var unarchived = false;

		function loadPage(fwd) {
			loading = true;
			end = false;

			if(xhr) {
				xhr.abort();
			}

			if(fwd) {
				loadersBottom.addClass('active');
				$('.load-next').removeClass('active');
			}
			else {
				loadersTop.addClass('active');
				$('.load-previous').removeClass('active');
			}

			var loadPage = page;
			if(!fwd) {
				topPage--;
				if(topPage < 0) {
					topPage = 0;
				}
				
				loadPage = topPage;
			}

			var params = { search: search, page: loadPage, order: order };

			if(order === 'random') {
				params.seed = randomSeed;
			}

            if(unarchived) {
                params.unarchived = unarchived;
            }

			xhr = api('galleries', params, function(result) {
				var collection = [ ];
				var galleries = result.galleries;
				var topWeight = null;

				$.each(galleries, function(i, gallery) {
					var item = $('.template .gallery-item').clone();
					item.data('gallery', gallery);

                    if(gallery.archived == 1) {
                        var url = '?' + $.param({ action: 'gallery', id: gallery.id, index: 0 });
                    }
                    else {
                        var url = 'http://exhentai.org/g/' + gallery.exhenid + '/' + gallery.hash;
                    }

                    item.prop('href', url);

					if(i == 0) {
						item.addClass('page-break');
						item.data('page', loadPage);
					}

                    if(gallery.archived == 0) {
                        item.addClass('unarchived');
                    }

					$('.title', item).text(gallery.name);
					$('.date', item).text(gallery.posted_formatted);

					if(gallery.ranked_weight) {
						if(topWeight === null) {
							topWeight = gallery.ranked_weight;
						}

						var pc = Math.round((gallery.ranked_weight / topWeight) * 100);
						$('.weight', item).show().text(pc + '%');
					}
					else {
						$('.weight', item).hide();
					}

					if(gallery.thumb) {
						if(gallery.thumb.landscape) {
							item.addClass('landscape');
						}

						item.css({
							backgroundImage: 'url(' + gallery.thumb.url + ')'
						})
					}
					else {
                        if(gallery.archived == 1) {
                            var url = 'api.php?' + $.param({ action: 'gallerythumb', id: gallery.id, index: 0, type: 1 });
                        }
                        else {
                            var url = 'api.php?' + $.param({ action: 'exgallerythumb', id: gallery.id });
                        }

						item.css({
							backgroundImage: 'url(' + url + ')'
						});
					}

					var tagList = $('.tags', item);
					renderTags(tagList, gallery.tags);

					collection.push(item);
				});

				if(fwd) {
					galleryList.append(collection);
				}
				else {
					galleryList.prepend(collection);
				}

				if(galleries.length > 0) {
					if(!history.state || history.state.action == 'galleries') {
						var state = buildHistoryState();
						state.data.page = loadPage;
						setHistoryState(true, state);
					}

					if(fwd) {
						page++;
					}
				}

				if(!result.end) {
					$('.load-next').addClass('active');
				}
				else {
					end = true;
				}

				var displayCount = $('.gallery-item', galleryList).length;
                var totalCount = result.meta.total;


                if(Intl && Intl.NumberFormat) {
                    displayCount = Intl.NumberFormat().format(displayCount);
                    totalCount = Intl.NumberFormat().format(totalCount);
                }

				searchCount.show().text('Displaying ' + displayCount + ' of ' + totalCount + ' results');

				if(topPage != 0) {
					$('.load-previous').addClass('active');
				}
				else {
					$('.load-previous').removeClass('active');
				}

				loadersTop.removeClass('active');
				loadersBottom.removeClass('active');

				loading = false;
			});
		}

        galleryList.on('click', '.gallery-item', function() {
            var galleryItem = $(this);
            var gallery = galleryItem.data('gallery');

            if(gallery.archived == 0) {
                var url = 'http://exhentai.org/g/' + gallery.exhenid + '/' + gallery.hash;
                window.open(url);
            }
            else {
                reader.trigger('loadgallery', [ gallery ]);    
            }

            return false;
        });

		function setHistoryState(replace, state) {
			var urlParams = { };
			if(state.data.search) urlParams.search = state.data.search;
			if(state.data.page && state.data.page > 0) urlParams.page = state.data.page;
			if(state.data.order != 'posted') urlParams.order = state.data.order;
			//if(state.data.seed) urlParams.seed = state.data.seed;
            if(state.data.unarchived) urlParams.unarchived = state.data.unarchived;

			var url = Object.keys(urlParams).length > 0 ? '?' + $.param(urlParams) : '/';

			if(replace) {
                history.replaceState(state, document.title, url);
			}
			else {
                history.pushState(state, document.title, url);
			}
		}

		function setHistory(replace) {
			var state = buildHistoryState();
			setHistoryState(replace, state);
		}

		function buildHistoryState() {
			var state = { action: 'galleries', data: { search: search, page: page - 1, order: order, unarchived: unarchived } };

			if(order === 'random') {
				state.data.seed = randomSeed;
			}

			return state;
		}

		galleryList.on('sethistory', function(e, replace) {
			setHistory(replace);
		});

		searchForm.submit(function() {
			search = $('.search', searchForm).val();
			searchCount.hide();
			galleryList.empty();
			page = 0;
			topPage = 0;
			randomiseSeed();
			loadPage(true);
			setHistory(false);

			return false;
		});

		function randomiseSeed() {
			randomSeed = Math.floor(Math.random() * 0x7ffffff).toString(36);
		}

        $('.unarchived').change(function() {
            unarchived = $(this).prop('checked');

            searchForm.submit();
        });

		$('.input-clear').click(function() {
			if(order === 'weight') {
				order = 'posted';
				setOrderLabel();
			}

			$('.search', searchForm).val('');
			searchForm.submit();
		});

		$('.search-order ul li', searchForm).click(function() {
			var trigger = $(this);
			order = trigger.data('order');

			var menu = $('.search-order', searchForm);
			$('.label', menu).text(trigger.text());

			var menuOuter = $('.menu-outer', menu);
			menuOuter.hide();
			setTimeout(function() { //hack
				menuOuter.removeAttr('style');
			}, 1);

			searchForm.submit();

			return false;
		});

		galleryList.on('click', '.tag', function() {
			var tag = $(this);
            var searchTag = tag.data('ns') + ':' + tag.data('tag');
            searchTag = escapeTag(searchTag);

			$('.search').val(searchTag);
			$('.search-form').submit();

			return false;
		});

		function setOrderLabel() {
			var label = $('.search-order .label', searchForm);

			if(order != 'posted' || label.text() != 'Order') {
				var orderOpt = $('.search-order li[data-order="' + order + '"]');
				label.text(orderOpt.text());
			}
		}

		galleryList.on('loadstate', function(e, data) {
			init = true;

			data = $.extend({ search: '', page: 0, order: order }, data);

			search = data.search;
			page = data.page;
			topPage = page;
			order = data.order;
			randomSeed = data.seed;
            unarchived = data.unarchived;

			if(!randomSeed && order === 'random') {
				randomiseSeed();
			}

			setOrderLabel();
            $('.unarchived').prop('checked', unarchived);

			$('.search').val(search);
			searchCount.hide();
			galleryList.empty();
			loadPage(true);

			if(!history.state || history.state.action != 'galleries') {
				setHistory(false);
			}
		});

		galleryList.on('init', function() {
			if(!init) {
				init = true;

				galleryList.trigger('loadstate', [ { page: 0, search: '' } ]);
			}

			if(!history.state || history.state.action != 'galleries') {
				setHistory(false);
			}
		});

		$('.load-previous .inner').click(function() {
			loadPage(false);
		});

		$('.load-next .inner').click(function() {
			loadPage(true);
		});

		var win = $(window);
		var doc = $(document);
		win.scroll(function() {
			if(!end) {
                var winHeight = win.height();
				if(win.scrollTop() + (winHeight * 1.2) >= doc.height()) {
					if(!loading) {
						loadPage(true);
					}
				}
			}
		});

		var pageUpdateProc = null;
		win.scroll(function() {
			clearTimeout(pageUpdateProc);

			if(!history.state || history.state.action == 'galleries') {
				pageUpdateProc = setTimeout(function() {
					if(!history.state || history.state.action == 'galleries') {
						var scroll = win.scrollTop() + win.height();
						var pageBreaks = $('.page-break', galleryList);
						var lastBreak = null;

						for(var i = 0; i < pageBreaks.length; i++) {
							var pageBreak = pageBreaks.eq(i);

							if(pageBreak.position().top > scroll) {
								break;
							}

							lastBreak = i;
						}

						if(lastBreak !== null) {
							var newPage = pageBreaks.eq(lastBreak).data('page');
							var state = history.state;
							state.data.page = newPage;
							setHistoryState(true, state);
						}
					}
				}, 100);
			}
		});
	});

	$('.search').each(function() {
		var input = $(this);
		var keywordXhr = null;
		var list = $('.suggestions');
		var term = null;
		var selectionStart = null;

		this.form.autocomplete = 'off';

		function getTerm() {
			return input.val().slice(0, selectionStart).trim();
		}

		input.keydown(function(e) {
			if(e.keyCode === 38) { //arrow up
				var selected = $('li.active', list);
				if(selected.length > 0) {
					if(selected.is(':not(:first-child)')) {
						selected.removeClass('active').prev().addClass('active');
					}
				}
				else {
					$('li', list).first().addClass('active');
				}

				return false;
			}
			else if(e.keyCode === 40) { //arrow down
				var selected = $('li.active', list);
				if(selected.length > 0) {
					if(selected.is(':not(:last-child)')) {
						selected.removeClass('active').next().addClass('active');
					}
				}
				else {
					$('li', list).first().addClass('active');
				}

				return false;
			}
			else if(e.keyCode === 13) { //enter
				var selected = $('li.active', list);
				if(selected.length > 0) {
					selected.click();
					return false;
				}

                list.empty();
                list.removeClass('active');
			}
			else if(e.keyCode === 27) { //esc
				list.empty();
				list.removeClass('active');
			}
		});

		input.keyup(function(e) {
			selectionStart = this.selectionStart;
			var newTerm = getTerm();

			if(keywordXhr) {
				keywordXhr.abort();
			}

			if(e.keyCode === 13 || e.keyCode === 27) { //enter, esc

			}
			else if(newTerm && newTerm.length > 1 && newTerm != term) {
				term = newTerm;

				keywordXhr = api('suggested', { term: term }, function(keywords) {
					list.empty();
					list.removeClass('active');

					if(keywords.length > 0) {
						var termBits = term.split(' ');

						for(var i in keywords) {
							var keyword = keywords[i];
							var itemHtml = keyword;
							var ignoreWord = false;

							for(var x in termBits) {
								var tempTerm = termBits.slice(x).join(' ');
								if(keyword.indexOf(tempTerm) >= 0) {
									if(keyword !== tempTerm) {
										var regex = new RegExp(tempTerm);
										itemHtml = itemHtml.split(regex).join('<span class="highlight">' + tempTerm + '</span>');
									}
									else {
										ignoreWord = true;
									}

									break;
								}
							}

							if(!ignoreWord) {
								var li = $('<li/>');
								li.data('keyword', keyword);
								li.html(itemHtml);
								li.appendTo(list);
							}
						}

						if($('> *', list).length > 0) {
							list.addClass('active');
						}
					}
				});
			}
			else if(getTerm() != term) {
				list.empty();
				list.removeClass('active');
			}
		});

		list.on('click', 'li', function() {
			var item = $(this);
			var keyword = item.data('keyword');
			var value = input.val();
			var pre = value.slice(0, selectionStart);

			var keywordBits = keyword.split(' ');
			var preBits = pre.split(' ').reverse();

			var newPre = '';

			for(var i in preBits) {
				if(i === 0) {
					continue;
				}

				var found = false;
				for(var x in keywordBits) {
					if(keywordBits[x].indexOf(preBits[i]) === 0) {
						found = true;
						delete keywordBits[x];
					}
				}

				if(!found) {
					newPre = preBits.slice(i).reverse().join(' ');
					newPre += ' ';
					break;
				}
			}

			newPre += escapeTag(keyword);

			if(newPre) {
				var newValue = newPre + value.slice(selectionStart);

				input.val(newValue);
				list.removeClass('active');
				input.focus();
				$('.search-form').submit();
			}

			return false;
		});

		function closeList() {
			if(keywordXhr) {
				keywordXhr.abort();
			}

			list.removeClass('active');
		}

		input.parent('form').submit(closeList);
	});
	
	var reader = $('.reader-container');
	reader.each(function() {
		var container = $(this);
		var imageHolder = $('.image-holder', container);
		var thumbsList = $('.gallery-thumbs-outer .thumbs', container);
		var infoContainer = $('.gallery-info', container);
		var preload = $('<img/>');
		var gallery = null;
		var currentIndex = 0;
		var keyShiftDown = false;
		var imageMouseDown = false;
		var hasResized = false;
		var scrollFactor = 5;
		var resizeFactor = 10;
		var firstImage = false;
		var endFlash = $('.end-flash');

		imageHolder.on('load', function() {
			if(this.width > 0 && this.height > 0) {
				var win = $(window);
				var pos = imageHolder.position();

				if(firstImage) {
					if(this.width > win.width()) {
						imageHolder.width(win.width());
					}
					
					if(this.height > win.height()) {
						imageHolder.width((this.width / this.height) * win.height());
					}

					imageHolder.css({
						left: (win.width() - this.width) / 2,
						top: (win.height() - this.height) / 2
					});

					imageHolder.removeClass('init');

					firstImage = false;
				}
				else if(this.height > win.height() || pos.top < 0) {
					imageHolder.stop().animate({
						top: 0
					}, Math.abs(pos.top) * 0.7);
				}

				imageHolder
					.data('origWidth', this.width)
					.data('origHeight', this.height);
			}

			preloadImage(currentIndex + 1);
		});

		function preloadImage(index) {
			if(index < gallery.numfiles && (index > preload.data('index') || !preload.data('index'))) {
				preload.data('index', index);
				preload.prop('src', getImageUrl(index));
			}
		}

		preload.load(function() {
			var index = preload.data('index');
			if(!index) {
				index = 0;
			}

			preloadImage(index + 1);
		});

		imageHolder.draggable({
			distance: 10
		});

		function getImageUrl(index) {
			var params = { action: 'archiveimage', id: gallery.id, index: index };
			
			var trueWidth = Math.round(window.devicePixelRatio * window.screen.availWidth);
			if(trueWidth <= 1000) {
				params.resize = trueWidth;
			}

			return 'api.php?' + $.param(params);
		}

		function loadImage(index, setHistory, replaceHistory) {
			if(gallery && index < gallery.numfiles) {
				var url = getImageUrl(index);
				imageHolder.prop('src', url);

				if(setHistory) {
					if(replaceHistory) {
						history.replaceState({ action: 'gallery', data: { gallery: gallery, index: index } }, document.title, '?' + $.param({ action: 'gallery', id: gallery.id, index: index }));
					}
					else {
						history.pushState({ action: 'gallery', data: { gallery: gallery, index: index } }, document.title, '?' + $.param({ action: 'gallery', id: gallery.id, index: index }));
					}
				}

				currentIndex = index;
			}
			else {
				endFlash.removeClass('transition').addClass('active');

				setTimeout(function() {
					endFlash.addClass('transition').removeClass('active');
				}, 0);
			}
		}

		endFlash.on('transitionend', function() {
			endFlash.removeClass('transition');
		});

		function close() {
			$('html').removeClass('reader-active');
			imageHolder.prop('src', '');
			gallery = null;
			$(window).off('.resize');
			preload.data('index', 0).prop('src', '');
			$('.gallery-list').trigger('init');
			$(document).off('mousewheel.reader');
		}

		$('.close', container).click(close);
		reader.on('close', close);

		$('.gallery-info .tags', container).on('click', '.tag', function() {
			var tag = $(this);
			var searchTag = tag.data('ns') + ':' + tag.data('tag');
            searchTag = escapeTag(searchTag);

			close();

			$('.gallery-list').trigger('loadstate', [ { search: searchTag } ]);
		});

		reader.on('loadstate', function(e, data) {
			if(!gallery || data.gallery.id != gallery.id) {
				loadGallery(data.gallery, data.index);
			}
			else {
				loadImage(data.index, false, false);
			}
		});

		function loadGallery(newGallery, index) {
			gallery = newGallery;

			imageHolder.width('auto').height('auto');
			imageHolder.addClass('init');

			$('html').addClass('reader-active');

			thumbsList.empty();
			for(var i = 0; i < gallery.numfiles; i++) {
				var url = 'api.php?' + $.param({ action: 'gallerythumb', id: gallery.id, index: i, type: 2 });

				var thumb = $('<div class="gallery-thumb"/>');
				thumb.data('index', i);
				thumb.css({
					backgroundImage: 'url(' + url + ')'
				});

				thumb.appendTo(thumbsList);
			}

			$('.title', infoContainer).text(gallery.name);
			if(gallery.origtitle && gallery.origtitle != gallery.name) {
				$('.origtitle', infoContainer).show().text(gallery.origtitle);
			}
			else {
				$('.origtitle', infoContainer).hide();
			}

			renderTags($('.tags', infoContainer), gallery.tags);

			index = parseInt(index);
			if(!index || index >= gallery.numfiles) {
				index = 0;
			}

			firstImage = true;
			if(history.state && history.state.action != 'gallery') {
				loadImage(index, true, false);
			}
			else {
				loadImage(index, true, true);
			}

			var win = $(window);
			win.on('resize.reader', function() {
				var width = win.width();
				var height = win.height();
				var oldWidth = win.data('oldWidth');
				var oldHeight = win.data('oldHeight');

				if(oldWidth && oldHeight) {
					imageHolder.css({
						top: '+=' + ((height - oldHeight) / 2),
						left: '+=' + ((width - oldWidth) / 2)
					});
				}

				win.data('oldWidth', width);
				win.data('oldHeight', height);
			});

			$(document).on('mousewheel.reader', onMousewheel);
		}

		container.on('loadgallery', function(e, newGallery, index) {
			loadGallery(newGallery, index);
		});

		$('.control-next').click(function() {
			loadImage(currentIndex + 1, true, false);
		});

		$('.control-prev').click(function() {
			loadImage(currentIndex - 1, true, false);
		});

		imageHolder.click(function(e) {
			if(e.button == 0 && !hasResized) {
				loadImage(currentIndex + 1, true, false);
			}

			imageMouseDown = false;
		});

		imageHolder.mouseup(function() {
			imageMouseDown = false;
		});

		imageHolder.mousedown(function() {
			imageMouseDown = true;
			hasResized = false;
		});

		thumbsList.on('click', '.gallery-thumb', function() {
			var index = $(this).data('index');
			loadImage(index, true, false);
		});

		function onMousewheel(e) {
			if(keyShiftDown || imageMouseDown) {
				var delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : -e.deltaY; //firefox/chrome use different when shift key is pressed

				var curWidth = imageHolder.width();
				var curHeight = imageHolder.height();
				var origWidth = imageHolder.data('origWidth');
				var origHeight = imageHolder.data('origHeight');
				var newWidth = curWidth - (delta * resizeFactor);
				var newHeight = (origHeight / origWidth) * newWidth;

				imageHolder.width(newWidth);
				imageHolder.css({
					top: '-=' + ((newHeight - curHeight) / 2),
					left: '-=' + ((newWidth - curWidth) / 2)
				});

				hasResized = true;

				return false;
			}
			else {
				imageHolder.css({
					top: '+=' + (e.deltaY * scrollFactor)
				});

				return false;
			}
		}

		$(document).on('keydown.reader keyup.reader', 'html.reader-active', function(e) {
			if(e.keyCode == 16) { //shift
				keyShiftDown = e.type == 'keydown';
			}
			else if(e.type == 'keydown') {
				if(e.keyCode == 39) { //key right
					loadImage(currentIndex + 1, true);
				}
				else if(e.keyCode == 37) { //key left
					loadImage(currentIndex - 1, true);
				}
			}
		});

		$('.actions-menu ul li', container).click(function() {
			var trigger = $(this);
			var action = trigger.data('action');

			if(action == 'delete') {
				var key = prompt('Enter access key');
				if(key) {
					api('deletegallery', { id: gallery.id, key: key }, function(data) {
						close();
					});
				}
			}
			else if(action == 'resize') {
				firstImage = true;
				imageHolder.width('auto').height('auto');
				imageHolder.trigger('load');
			}
			else if(action == 'download') {
				var url = '/api.php?' + $.param({ action: 'download', id: gallery.id });
				window.open(url);
			}
			else if(action == 'similar') {
				var tagList = [ ];
				for(var ns in gallery.tags) {
					for(var i in gallery.tags[ns]) {
						var tag = ns + ':' + gallery.tags[ns][i];

						tag = tag.replace('"', '\\\\"');
						tag = '"' + tag + '"';

						tagList.push(tag);
					}
				}

				close();

				var search = tagList.join(' | ');

				$('.gallery-list').trigger('loadstate', [ { search: search, order: 'weight' } ]);
			}
			else if(action == 'original') {
				var url = 'http://exhentai.org/g/' + gallery.exhenid + '/' + gallery.hash;
                window.open(url);
			}

			return false;
		});

		/*
		function setupTouchResize() {
			var origWidth = null;
			var origHeight = null;
			var pos = null;

			new Hammer(imageHolder.get(0), { preventDefault: true, gesture: true }).on('touch pinch drag tap', function(e) {
				if(e.type === 'touch') {
					origWidth = imageHolder.width();
					origHeight = imageHolder.height();
					pos = imageHolder.position();
				}
				else if(e.type === 'pinch') {
					var newWidth = origWidth * e.gesture.scale;
					
					if(newWidth < 300) {
						newWidth = 300;
					}

					imageHolder.width(newWidth);
				}
				else if(e.type === 'drag') {
					imageHolder.css({
						top: pos.top + e.gesture.deltaY,
						left: pos.left + e.gesture.deltaX
					});
				}
				else if(e.type === 'tap') {
					//loadImage(currentIndex + 1, true, false);
				}

				return true;
			});
		}

		setupTouchResize();
		*/
	});

	function setupDropdowns() {
		var dropdowns = $('.dropdown-button');

		dropdowns.click(function() {
			$(this).toggleClass('active');
		});

		$(document).click(function(e) {
			if(dropdowns.hasClass('active') && dropdowns.has(e.target).length === 0) {
				dropdowns.removeClass('active');
			}
		});

		$('ul li', dropdowns).click(function() {
			$(this).parents('.dropdown-button').removeClass('active');
		});
	}

	setupDropdowns();

	$(window).on('popstate', function(e) {
		if(e.originalEvent.state) {
			switch(e.originalEvent.state.action) {
				case 'galleries': {
					reader.trigger('close');
					$('.gallery-list').trigger('loadstate', [ e.originalEvent.state.data ]);
					break;
				}
				case 'gallery': {
					reader.trigger('loadstate', [ e.originalEvent.state.data ]);
					break;
				}
			}
		}
	});

	if(window.location.search == '') {
		$('.gallery-list').trigger('init');
	}
	else {
		var query = decodeQuery();
		if(!query.action || query.action == 'galleries') {
			$('.gallery-list').trigger('loadstate', [ { page: query.page, search: query.search, order: query.order, seed: query.seed, unarchived: query.unarchived } ]);
		}
		else if(query.action == 'gallery') {
			api('gallery', { id: query.id }, function(gallery) {
				reader.trigger('loadgallery', [ gallery, query.index ])
			});
		}
	}

	function decodeQuery() {
		var ret = {};
		var bits = window.location.search.slice(1).replace(/\+/g, '%20').split('&');
		for(var i in bits)
		{
			var keyvalue = bits[i].split('=');
			ret[decodeURIComponent(keyvalue[0])] = keyvalue.length == 1 ? false : decodeURIComponent(keyvalue[1]);
		}

		return ret;
	}
});
