//### init ###

$(document).ready(function(){

    function minheight(){
        var h = $(window).height();
        h = h-356; //was 345
        chat.chatContainer.css('height',h);
        if(chat.chatIsScrolledToEnd) {
            var objDiv = document.getElementById("chat-container");
            objDiv.scrollTop = objDiv.scrollHeight;
        }
    }
    //todo: оптимизация - выполнять только если существует контейнер чата (сейчас: весь скрипт грузится только на странице чата)
    window.onresize = function() {minheight()};
    minheight();

    chat.Init();
});



//### chat class ###

function Chat(id) {
    this.companionId            = id || '';

    this.chatTextarea           = $("#chat-textarea");
    this.chatWrapper            = $("#chat-wrapper");
    this.chatForm               = $("#chat-form");
    this.chatContainer          = $('#chat-container');
    this.dialogsWrapper         = $("#dialogs-wrapper");
    this.privateCounter         = $("#private");
    this.addsPreloader          = $("#adds-preloader");

    this.loaderAddsState        = true;
    this.chatIsScrolledToEnd    = true;

    this.chatIsScrolledToStart  = false;
    this.addsPreloaderBlock     = false;

    //observer
    //members

    this.hasBeenViewed = function(data) {
        $("#msg" + data.id).removeClass('unread');
        $("#user" + data.rcpt).removeClass('myunread');
    };

    this.allIsViewed = function(data) {
        $("#chat-wrapper .unread").removeClass('unread');
        $("#user" + data.from).removeClass('myunread');
    };

    this.writing = function(data) {
        //data.Rcpt ---- chat id
        if(data.rcpt === this.companionId){
            $('.writing-animation').show();
            setTimeout(function() { $('.writing-animation').hide(); }, 5200);
        }
    };

    this.incPrivate = function() {
        chat.privateCounter.html(+chat.privateCounter.html() + 1);
        chat.privateCounter.removeClass('hide');
    };

    this.decPrivate = function() {
        var res = +chat.privateCounter.html() - 1;
        chat.privateCounter.html(res);
        if (res === 0) {
            chat.privateCounter.addClass('hide');
        }
    };

    this.refreshPrivate = function(data) {
        chat.privateCounter.html(data.body);
    };

    this.send = function(text) {
        var rcpt = chat.companionId;
        var body = text;
        var action = 'sendMessage';

        var data = {
            "action": action,
            "body": body,
            "rcpt": rcpt
        };

        if (socket.readyState === 1){
            socket.send(JSON.stringify(data));
        } else {
            alert('Websocket server is closed.');
        }
    };

    this.messageFactory = function(data) {
        var ava         = '/images/avatars/' + data.ava;
        var body        = emoji.emojiToHtml(data.body);
        var messageId   = data.id;
        var isFromMe    = data.from === data.observer;
        var viewed      = (data.viewed) ? '': ' unread';
        var companion   = (data.from === data.observer) ? data.rcpt : data.from;

        var chatLastMessageIsSelf = $("#chat-wrapper .message-wrapper:last").hasClass("my");

        if(!isFromMe && !data.viewed){
            var msg = {
                "action": 'viewMessage',
                "id": messageId
            };

            if (socket.readyState === 1) {
                socket.send(JSON.stringify(msg));
                viewed = '';
            }
        }

        var message = '';
        var m = '';

        message += '<div class="chat-message' + viewed + '" id="msg' + messageId + '">';
        message += body;
        message += '</div>';

        if($('.chat-message').length == 0){
            if(isFromMe){
                m += '<div class="message-wrapper my">';
                m += '<div class="message-avatar">';
                m += '<img class="circle" src="' + ava + '" alt="avatar">';
                m += '</div>';
                m += '<div class="message-stack">';
                m += message;
                m += '</div>';
                m += '</div>';
                $("#chat-wrapper").append(m);
            } else {
                m += '<div class="message-wrapper">';
                m += '<div class="message-avatar">';
                m += '<img class="circle" src="' + ava + '" alt="avatar">';
                m += '</div>';
                m += '<div class="message-stack">';
                m += message;
                m += '</div>';
                m += '</div>';
                $("#chat-wrapper").append(m);
            }
        } else {
            if(!chatLastMessageIsSelf){
                if(isFromMe){
                    m += '<div class="message-wrapper my">';
                    m += '<div class="message-avatar">';
                    m += '<img class="circle" src="' + ava + '" alt="avatar">';
                    m += '</div>';
                    m += '<div class="message-stack">';
                    m += message;
                    m += '</div>';
                    m += '</div>';
                    $("#chat-wrapper").append(m);
                } else {
                    $("#chat-wrapper .message-stack:last").append(message);
                }
            } else {
                if(isFromMe){
                    $("#chat-wrapper .message-stack:last").append(message);
                } else {
                    m += '<div class="message-wrapper">';
                    m += '<div class="message-avatar">';
                    m += '<img class="circle" src="' + ava + '" alt="avatar">';
                    m += '</div>';
                    m += '<div class="message-stack">';
                    m += message;
                    m += '</div>';
                    m += '</div>';
                    $("#chat-wrapper").append(m);
                }
            }
        }


        $("#user" + companion).html(body);

        if (!data.viewed && data.observer === data.from) {
            $("#user" + companion).addClass('myunread');
        }

        if (data.observer === data.from) {
            $("#user" + companion).addClass('prefix');
        }
    };

    this.messagesPackHandler = function(data, observer, opponent) {
        var t = this;
        var count = 0;
        data.forEach(function(item, i, arr) {
            //alert( i + ": " + item + " (массив:" + arr + ")" );
            //data[i].observer = observer.id;
            data[i].ava = String(data[i].from) + '.gif?t=' + String(Date.now()).slice(0, -4);
            t.messageFactoryBack(data[i]);
            count++;
        });

        if (count < 20) {
            t.addsPreloader.detach().prependTo('#chat-wrapper');
            t.addsPreloader.html('<div> </div>');
        } else {
            t.addsPreloader.detach().prependTo('#chat-wrapper');
        }

        this.scrollToEnd();
    };

    this.scrollToEnd = function() {
        var objDiv = document.getElementById("chat-container");
        objDiv.scrollTop = objDiv.scrollHeight;
    };

    this.messageFactoryBack = function(data) {

        var ava         = '/images/avatars/' + data.ava;
        var body        = emoji.emojiToHtml(data.body);
        var messageId   = data.id;
        var isFromMe    = data.from === data.observer;
        var viewed      = (data.viewed) ? '': ' unread';
        //var companion   = (data.from === data.observer) ? data.rcpt : data.from;

        var chatLastMessageIsSelf = $("#chat-wrapper .message-wrapper:first").hasClass("my");

        var changeSide = false;

        if(!isFromMe && !data.viewed){
            var msg = {
                "action": 'viewMessage',
                "id": messageId
            };

            if (socket.readyState === 1) {
                socket.send(JSON.stringify(msg));
                viewed = '';
            }
        }

        var message = '';
        var m = '';

        message += '<div class="chat-message' + viewed + '" id="msg' + messageId + '">';
        message += body;
        message += '</div>';

        if($('.chat-message').length == 0){
            if(isFromMe){
                m += '<div class="message-wrapper my">';
                m += '<div class="message-avatar">';
                m += '<img class="circle" src="' + ava + '" alt="avatar">';
                m += '</div>';
                m += '<div class="message-stack">';
                m += message;
                m += '</div>';
                m += '</div>';
                $("#chat-wrapper").prepend(m);
            } else {
                m += '<div class="message-wrapper">';
                m += '<div class="message-avatar">';
                m += '<img class="circle" src="' + ava + '" alt="avatar">';
                m += '</div>';
                m += '<div class="message-stack">';
                m += message;
                m += '</div>';
                m += '</div>';
                $("#chat-wrapper").prepend(m);
            }
        } else {
            if (!chatLastMessageIsSelf) {
                if (isFromMe) {
                    m += '<div class="message-wrapper my">';
                    m += '<div class="message-avatar">';
                    m += '<img class="circle" src="' + ava + '" alt="avatar">';
                    m += '</div>';
                    m += '<div class="message-stack">';
                    m += message;
                    m += '</div>';
                    m += '</div>';

                    $("#chat-wrapper").prepend(m);
                    changeSide = true;
                } else {
                    $("#chat-wrapper .message-stack:first").prepend(message);
                }
            } else {
                if (!isFromMe) {
                    m += '<div class="message-wrapper">';
                    m += '<div class="message-avatar">';
                    m += '<img class="circle" src="' + ava + '" alt="avatar">';
                    m += '</div>';
                    m += '<div class="message-stack">';
                    m += message;
                    m += '</div>';
                    m += '</div>';
                    $("#chat-wrapper").prepend(m);
                    changeSide = true;
                } else {
                    $("#chat-wrapper .message-stack:first").prepend(message);
                }
            }
        }
        return changeSide;
    };

    this.dialogFactory = function(data) {
        var ava         = '/images/avatars/' + data.ava;
        var body        = emoji.emojiToHtml(data.last_message_body);
        var active      = (data.current) ? ' active' : '';
        var firstname   = data.firstname;
        var lastname    = data.lastname;
        var companion   = data.companion;
        var isOnline    = (data.online) ? ' online' : '';
        var unread      = (!data.viewed && data.observer !== data.last_id) ? ' unread' : '';
        var myunread    = (!data.viewed && data.observer === data.last_id) ? ' myunread' : '';

        var prefix      = (data.observer === data.last_id) ? ' prefix' : '';

        var dialog = '';

        dialog += '<li style="cursor: pointer;" onclick="document.location.href=\'/chat/' + companion + '\';" class="collection-item avatar' + active + unread + '">';
        dialog += '<div class="badged-circle' + isOnline + '">';
        dialog += '<img class="circle" src="' + ava + '" alt="avatar">';
        dialog += '</div>';
        dialog += '<span class="title">' + firstname + ' ' + lastname + '</span>';
        dialog += '<p class="truncate' + myunread + prefix + '" id="user' + companion + '">' + body + '</p>';
        dialog += '</li>';

        return dialog;
    };

    this.getMessages = function(id) {
        if (socket.readyState === 1) {
            socket.send('{"action": "getMessages","rcpt":' + id + '}');
        } else {
            //alert("долгая загрузка");
            setTimeout(function() {
                if (socket.readyState === 1) {
                    socket.send('{"action": "getMessages","rcpt":' + id + '}');
                } else {
                    setTimeout(function() {
                        if (socket.readyState === 1) {
                            socket.send('{"action": "getMessages","rcpt":' + id + '}')
                        }

                    }, 3000);
                }
            }, 1000);
        }
    };

    this.Init = function() {
        id = chat.companionId;

        //init handlers
        var t = this;
        this.chatTextarea.keyup(function (event) {
            t.textarea(event);
        }).bind(null, t);

        //console.log(this.chatTextarea);

        this.chatTextarea.keypress(function (event) {
            var msg = {
                "action": 'writing',
                "rcpt": id
            };
            socket.send(JSON.stringify(msg));
        }).bind(null, t);

        this.chatContainer.scroll(function () {
            t.scroll();
        }).bind(null, t);

        //данные
        var myHeaders = new Headers();
        myHeaders.append("X-Requested-With", "XMLHttpRequest");

        //загрузка сообщений
        this.getMessages(id);

        fetch('/ajax/chat/' + id, {method: "GET",  headers: myHeaders, credentials: "include"})
            .then(
                function(response) {
                    if (response.status >= 500 || response.status < 200) {
                        alert('Looks like there was a problem. Status Code: ' +
                            response.status);
                        return;
                    }

                    response.json().then(function(json) {
                        var data = (typeof(json) == 'object') ? json : JSON.parse(json);

                        if (t.hasOwnProperty('dialogs') && data.dialogs.hasOwnProperty('status') && data.dialogs.status === 'fail') {
                            //none
                        } else {
                            var dialogs = data.dialogs;
                            console.log(dialogs);

                            for (var key in dialogs) {
                                var dialog = t.dialogFactory(dialogs[key]);
                                t.dialogsWrapper.append(dialog);
                            }

                        }

                        /*
                        if (data.messages.hasOwnProperty('status') && data.messages.status === 'fail') {
                            //none
                        } else {
                            var messages = data.messages;
                            console.log(messages);

                            var i = 0;
                            for (var key in messages) {
                                t.messageFactory(messages[key]);
                                i++;
                            }

                            if (i < 30) {
                                t.addsPreloader.html('<div> </div>');
                            }

                            var objDiv = document.getElementById("chat-container");
                            objDiv.scrollTop = objDiv.scrollHeight;
                        }
                        */
                    });
                }
            )
            .catch(function(err) {
                alert('Ajax error. Please send report to administration about this problem.');
                console.log(err);
            });
    };

    this.textarea = function(event) {
        if (event.keyCode == 13 && event.ctrlKey) {
            var content = this.chatTextarea[0].innerText;
            var caret = getTextSelection(this.chatTextarea[0]);
            caret = caret.end;
            console.log('content length: '+ content.length);
            console.log('careet: '+ caret);
            console.log('current line: '+getSelectionCaretAndLine(this.chatTextarea[0]).line);
            var currentLine = getSelectionCaretAndLine(this.chatTextarea[0]).line;
            this.chatTextarea[0].innerText = content.substring(0,caret)+"\n"+content.substring(caret,content.length);
            //set caret to this pos + 1
            setCaret(this.chatTextarea[0],currentLine+1, currentLine+1, 0, 0);
            
            event.preventDefault();
            console.log('enter+ctrl');
        }else if(event.keyCode == 13)
        {
            var text = emoji.htmlToEmoji(this.chatTextarea[0].innerHTML);
            text = emoji.cleanUp(text);
            var regex = /<br[^>]*>/g;
            text = text.replace(regex, "\n");
            //this.send(text.replace(/&nbsp;/g, ' '));

            this.send(emoji.escapeHtml(text));
            this.chatTextarea[0].innerText = "";
            console.log('enter');
        }
    };

    this.scroll = function() {
        var maxScrollH = (this.chatWrapper.height() - $(window).height()) + 410;
        this.chatIsScrolledToEnd = ($(this.chatContainer).scrollTop() >= maxScrollH - 110);
        this.chatIsScrolledToStart = ($(this.chatContainer).scrollTop() <= 60);
        if (this.chatIsScrolledToStart && this.loaderAddsState && !this.addsPreloaderBlock) {
            this.chatContainer.scrollTop(0);
            this.loaderAddsState = false;
            var first = $("#chat-wrapper .chat-message:first").attr( "id").slice(3);

            if (socket.readyState === 1) {
                socket.send('{"action" : "getMessages", "id" : ' + first + ', "rcpt":' + id + '}');
            }
        }
    };

    this.loadAdds = function(data) {
        var firstMessageId = $("#chat-wrapper .chat-message:first").attr( "id").slice(3);

        var t = this;

        var messages = JSON.parse(data.body);
        var ob = JSON.parse(data.params.observer);
        var op = JSON.parse(data.params.opponent);
        console.log(messages);

        var currentPos = t.chatContainer.scrollTop();

        if(messages.length === 0) {
            t.addsPreloaderBlock = true;
        }

        var i = 0;
        var changeSide = 0;
        var counter = 0;
        var avaOffset = 0;

        for (var key in messages) {
            var m = messages[key];

            m.ava = String(m.from) + '.gif?t=' + String(Date.now()).slice(0, -4);
            if(chat.messageFactoryBack(m, ob, op)) {
                changeSide++;
                if(i === 0) {
                    //changeSide = changeSide-2;
                }
                if(counter === 1) {
                    avaOffset++;
                }
                counter = 0;
            } else {
                counter++;
            }

            i++;
        }

        if (i < 20) {
            t.addsPreloader.detach().prependTo('#chat-wrapper');
            t.addsPreloader.html('<div> </div>');
        } else {
            t.addsPreloader.detach().prependTo('#chat-wrapper');
        }

        if (i) {
            var offset = (changeSide * 17) + (avaOffset * 3);

            console.log(changeSide);
            console.log(avaOffset);
            if(i <10) {
                //cst = 17;
            }
            var h = currentPos + offset;
            $(".chat-message:lt(" + i + ")").each(function () {

                h = +h + $(this).outerHeight(true);
            });

            if(h > 1) {
                //alert(h);
                t.chatContainer.scrollTop(h);
            }
        } else {

        }

        this.loaderAddsState = true;
    };
}

var tempvar001 = (temp = parseInt(window.location.pathname.split('/')[2])) ? temp : '' ; // временная хуета
var chat = new Chat(tempvar001); //chat.function()


function getSelectionCaretAndLine (editable) {

    // collapse selection to end
    window.getSelection().collapseToEnd();

    const sel = window.getSelection();
    const range = sel.getRangeAt(0);

    // get anchor node if startContainer parent is editable
    let selectedNode = editable === range.startContainer.parentNode
        ? sel.anchorNode
        : range.startContainer.parentNode;

    if (!selectedNode) {
        return {
            caret: -1,
            line: -1,
        };
    }

    // in case there is nested doms inside editable
    while(selectedNode.parentNode !== editable) {
        selectedNode = selectedNode.parentNode;
    }

    // select to top of editable
    range.setStart(editable.firstChild, 0);

    // do not use 'this' sel anymore since the selection has changed
    const content = window.getSelection().toString();
    const text = JSON.stringify(content);
    const lines = (text.match(/\\n/g) || []).length + 1;

    // clear selection
    window.getSelection().collapseToEnd();

    // minus 2 because of strange text formatting
    return {
        caret: text.length - 2,
        line: lines
    }
}
function setCaret(el, startNodeIndex, endNodeIndex, start, end){
    var range = document.createRange();
    var sel = window.getSelection();
    range.setStart(el.childNodes[startNodeIndex], start);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
}

function getTextSelection (el) {
    const selection = window.getSelection();

    if (selection != null && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);

        return {
            start: getTextLength(el, range.startContainer, range.startOffset),
            end: getTextLength(el, range.endContainer, range.endOffset)
        };
    } else
        return null;
}

function getTextLength (parent, node, offset) {
    var textLength = 0;

    if (node.nodeName == '#text')
        textLength += offset;
    else for (var i = 0; i < offset; i++)
        textLength += getNodeTextLength(node.childNodes[i]);

    if (node != parent)
        textLength += getTextLength(parent, node.parentNode, getNodeOffset(node));

    return textLength;
}

function getNodeTextLength (node) {
    var textLength = 0;

    if (node.nodeName == 'BR')
        textLength = 1;
    else if (node.nodeName == '#text')
        textLength = node.nodeValue.length;
    else if (node.childNodes != null)
        for (var i = 0; i < node.childNodes.length; i++)
            textLength += getNodeTextLength(node.childNodes[i]);

    return textLength;
}

function getNodeOffset (node) {
    return node == null ? -1 : 1 + getNodeOffset(node.previousSibling);
}