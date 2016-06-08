<?php
/*
Plugin Name: TTXML Importer
Plugin URI: http://ani2life.com
Description: Will process a TTXML for importing posts into WordPress. TTXML(티스토리/텍스트큐브 백업파일)의 내용을 워드프레스로 가져오는 Importer.
Author: A2
Version: 2.5
Author URI: http://ani2life.com
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*
TTXML Importer.
Will process a TTXML for importing posts into WordPress.
Copyright (C) 2009-2013 박민권, ani2life@gmail.com

이 프로그램은 자유 소프트웨어입니다. 소프트웨어의 피양도자는 자유 소프트웨어 재단이 공표한 GNU 일반 공중 사용 허가서 2판 또는 그 이후 판을 임의로 선택해서, 그 규정에 따라 프로그램을 개작하거나 재배포할 수 있습니다.

이 프로그램은 유용하게 사용될 수 있으리라는 희망에서 배포되고 있지만, 특정한 목적에 맞는 적합성 여부나 판매용으로 사용할 수 있으리라는 묵시적인 보증을 포함한 어떠한 형태의 보증도 제공하지 않습니다. 보다 자세한 사항에 대해서는 GNU 일반 공중 사용 허가서를 참고하시기 바랍니다.

GNU 일반 공중 사용 허가서는 이 프로그램과 함께 제공됩니다. 만약, 이 문서가 누락되어 있다면 자유 소프트웨어 재단으로 문의하시기 바랍니다. (자유 소프트웨어 재단: Free Software Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA)
*/

if ( !defined('WP_LOAD_IMPORTERS') ) return;

// Load Importer API
require_once ABSPATH.'wp-admin/includes/import.php';

class TTXML_Import {
    var $read_len = 8192; // 1024*8
    var $minimum_len = 13;
    var $importdata = '';

    var $file;
    var $filesize;
    var $fp;

    var $attach_dir;
    var $attach_url;

    var $import_count = 0;
    var $already_count = 0;

    // settings
    var $attach_subdir = '1';
    var $attach_overwrite = true;

    function header() {
        echo '<div class="wrap">';
        screen_icon();
        echo '<h2>'.__('Import TTXML').'</h2>';
    }

    function footer() {
        echo '</div>';
    }

    function greet() {
        echo '<div class="narrow">';
        echo __('TTXML(티스토리/텍스트큐브 백업파일)의 내용을 워드프레스로 가져올 수 있습니다.').'<br />';
        echo __('백업파일에 포함된 첨부파일이 저장되는 디렉토리').': '.$this->attach_dir.'<br />';
        echo __('텍스트큐브의 첨부파일을 위의 디렉토리에 직접 복사하셔도 됩니다.');


        echo '<h4>'.__('업로드 방식').'</h4>';
	echo '<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form" action="admin.php?import=ttxml&amp;step=1">';
        echo '<p><label>';
        echo '<input type="checkbox" name="slug" checked value="num" />'.__('숫자로된 Tistory URL 그대로 옮겨오기 예: id.tistory.com/1');
        echo '</label></p>';
	echo '<p><label for="upload">컴퓨터에서 파일 찾기:</label> (최대 크기: 2 MB)';
	echo '<input id="upload" name="import" size="25" type="file">';
	echo '</p>';
	echo '<p class="submit"><input disabled="" name="submit" id="submit" class="button button-primary" value="파일 업로드 후 가져오기" type="submit"></p></form>';

        echo '<h4>'.__('주소입력 방식').'</h4>';
        echo '<form action="admin.php?import=ttxml&amp;step=1" method="post">';
        echo '<p>';
	echo '<label>';
	echo '<input type="checkbox" name="slug" checked value="num" />'.__('숫자로된 Tistory URL 그대로 옮겨오기 예: id.tistory.com/1');
        echo '</label>';
        echo '<p>';
        echo __('웹서버 로컬 경로 또는 http://가 포함된 URL의 백업파일도 가능합니다.');
        echo '</p>';
        echo '<label>'.__('백업파일의 위치').':';
        echo ' (ex: '.$this->attach_dir.' , http://foo.com/backup.xml)<br />';
        echo '<input type="text" size="40" name="filepath" />';
        echo '</label>';
        echo '<input type="submit" value="Summit" />';
        echo '</p>';
        echo '</form>';
        echo '<h4>'.__('참고사항').'</h4>';
        echo '<ul>';
        echo '<li>'.__('백업파일의 크기가 클수록 처리시간이 길어집니다.').'</li>';
        echo '<li>'.__('첨부파일을 백업파일에 포함하지 않고 직접 복사하면 섬네일이 만들어지지 않습니다.').'</li>';
        echo '<li>'.__('워드프레스는 비밀댓글 기능이 없어서 비밀댓글이 모두 공개됩니다.').'</li>';
        echo '</ul>';
        echo '</div>';
    }

    function message($msg) {
        echo "{$msg}<br />";
        flush();
    }

    function replacer_change(&$post) {
        static $align_map = array('L'=>'left', 'C'=>'center', 'R'=>'right');

        $post = str_replace('[##_ATTACH_PATH_##]', $this->attach_url, $post);
        $post = str_replace('http://tt_attach_path', $this->attach_url, $post);

        preg_match_all('/\[##_(.+?)_##]/s', $post, $replacers);

        foreach ( $replacers[1] as $i=>$replacer ) {
            $replacer = preg_replace('/(\[##_|_##\])/', '', $replacer);
            $vals = explode('|', $replacer);

            if ( $vals[0] == 'Gallery' ) {
                $count = count($vals);
                preg_match('/width="[^"]+"/', $vals[$count-1], $width);
                $width = $width[0];

                $replace_str = array();

                for ( $j = 1; $j < $count-1; $j += 2 ) {
                    $src = "{$this->attach_url}/{$vals[$j]}";
                    $class = ( $vals[$j+1] === '' ) ? " class=\"aligncenter\"" : '';

                    $replace_tmp = "<img src=\"{$src}\"{$class} {$width} />";

                    // image caption
                    if ( $vals[$j+1] ) {
                        $caption = $vals[$j+1];
                        $replace_tmp = "[caption id=\"\" align=\"aligncenter\" {$width} caption=\"{$caption}\"]{$replace_tmp}[/caption]";
                    }

                    $replace_str[] = $replace_tmp;
                }

                $replace_str = implode('', $replace_str);
            } else {
                preg_match('/1([LCR])/', $vals[0], $align);
                $align = $align_map[$align[1]];
                $src = "{$this->attach_url}/{$vals[1]}";
                $class = ( $vals[3] === '' ) ? " class=\"align{$align}\"" : '';

                if ( preg_match('/\.(jpg|jpeg|png|gif)$/i', $vals[1]) ) {
                    $replace_str = "<img src=\"{$src}\"{$class} {$vals[2]} />";
                } else {
                    $replace_str = "<a href=\"{$src}\"{$class} {$vals[2]} />{$vals[1]}</a>";
                }

                // image caption
                if ( $vals[3] ) {
                    $caption = $vals[3];
                    preg_match('/width="[^"]+"/', $vals[2], $width);
                    $width = $width[0];
                    $replace_str = "[caption id=\"\" align=\"align{$align}\" {$width} caption=\"{$caption}\"]{$replace_str}[/caption]";
                }
            }

            $post = str_replace($replacers[0][$i], $replace_str, $post);
        }
    }

    function import_post(&$post) {
        extract($post);

        if ( post_exists($post_title, $post_content, $post_date) ) {
            return 0;
        }

        $post_id = wp_insert_post($post);
        if ( !$post_id ) return -1;

        if ($category)
            wp_create_categories(array($category), $post_id);

        return $post_id;
    }

    function import_comments($post_id, &$comments) {
        // insert comments
        foreach ($comments as $data) {
            $data['comment_post_ID'] = $post_id;
            $comment_id = wp_insert_comment($data);
            if (!$comment_id) return false;

            // insert replys
            foreach ($data['replys'] as $reply) {
                $reply['comment_post_ID'] = $post_id;
                $reply['comment_parent'] = $comment_id;
                if ( !wp_insert_comment($reply) ) return false;
            }
        }

        return true;
    }

    function import_attachments($post_id, &$attachments) {
        $thumbnail_once = false;

        foreach ( $attachments as $data ) {
            // image only
            if ( strpos($data['post_mime_type'], 'image/') !== 0 )
                continue;

            $data['guid'] = $this->attach_url.'/'.$data['post_name'];
            $data['post_status'] = 'inherit';
            $data['post_content'] = '';

            $file_path = $this->attach_dir.'/'.$data['post_name'];

            $attach_id = wp_insert_attachment($data, $file_path, $post_id);
            if ( !$attach_id ) return false;

            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            if ( !$thumbnail_once ) {
                update_post_meta($post_id, '_thumbnail_id', $attach_id);
                $thumbnail_once = true;
            }
        }

        return true;
    }

    function & parse_comments(&$data) {
        global $wpdb;

        preg_match_all('|<comment>(.+?(?:<comment>.+?</comment>)*)\s*</comment>|s', $data, $comments);
        $comments = $comments[1];

        if (0 == count($comments)) return array();

        $comment_datas = array();
        foreach ( $comments as $comment ) {
            preg_match('|<ip>([^<]+)</ip>|s', $comment, $comment_author_IP);
            $comment_author_IP = $comment_author_IP[1];

            preg_match('|<written>([^<]+)</written>|s', $comment, $comment_date);
            $comment_date_gmt = gmdate('Y-m-d H:i:s', $comment_date[1]);
            $comment_date = date('Y-m-d H:i:s', $comment_date[1]);

            preg_match('|<name>([^<]+)</name>|s', $comment, $comment_author);
            $comment_author = $comment_author[1];

            preg_match('|<homepage>([^<]*)</homepage>|s', $comment, $comment_author_url);
            $comment_author_url = $comment_author_url[1];

            preg_match('|<content>([^<]+)</content>|s', $comment, $comment_content);
            $comment_content = htmlspecialchars_decode(trim($comment_content[1]));
            $comment_content = $wpdb->escape($comment_content);

            $comment_approved = 1;
            $user_id = 0;

            $replys = &$this->parse_comments($comment);

            $comment_datas[] = compact(
                'comment_author_IP', 'comment_date', 'comment_date_gmt',
                'comment_author', 'comment_author_url', 'comment_content',
                'comment_approved', 'user_id', 'replys'
            );
        }

        return $comment_datas;
    }

    function & parse_trackbacks(&$data) {
        global $wpdb;

        $match_count = preg_match_all('|<trackback>(?:.(?<!</trackback>))+</trackback>|s', $data, $trackbacks);
        $trackbacks = $trackbacks[0];

        if ( empty($trackbacks) ) return array();

        $comment_datas = array();
        foreach ( $trackbacks as $trackback ) {
            preg_match('|<ip>([^<]+)</ip>|s', $trackback, $comment_author_IP);
            $comment_author_IP = $comment_author_IP[1];

            preg_match('|<received>([^<]+)</received>|s', $trackback, $comment_date);
            $comment_date_gmt = gmdate('Y-m-d H:i:s', $comment_date[1]);
            $comment_date = date('Y-m-d H:i:s', $comment_date[1]);

            preg_match('|<site>([^<]+)</site>|s', $trackback, $comment_author);
            $comment_author = $comment_author[1];

            preg_match('|<url>([^<]*)</url>|s', $trackback, $comment_author_url);
            $comment_author_url = $comment_author_url[1];

            preg_match('|<excerpt>([^<]+)</excerpt>|s', $trackback, $comment_content);
            $comment_content = htmlspecialchars_decode(trim($comment_content[1]));
            $comment_content = $wpdb->escape($comment_content);

            $comment_approved = 1;
            $user_id = 0;
            $comment_type = 'trackback';

            $replys = array();

            $comment_datas[] = compact(
                'comment_author_IP', 'comment_date', 'comment_date_gmt',
                'comment_author', 'comment_author_url', 'comment_content',
                'comment_approved', 'user_id', 'comment_type', 'replys'
            );
        }

        return $comment_datas;
    }

    function & parse_post(&$data) {
        global $wpdb;

        $status_map = array(
            'public'=>'publish', 'syndicated'=>'publish',
            'private'=>'private', 'protected'=>'publish'
        );

        $post_author = 1;

        $post_type = (strpos($data, '<post') === 0) ? 'post' : 'page';

        if ( $post_type == 'post' ) {
	    if($_POST['slug'] == 'num' )  preg_match('|<id>([^<]+)</id>|s', $data, $post_name);
	    else preg_match('|^<post slogan="([^"]+)"|s', $data, $post_name);
            $post_name = trim($post_name[1]);
            $post_name = $wpdb->escape($post_name);
        } else {
            $post_name = null;
        }

        preg_match('|<title>([^<]+)</title>|s', $data, $post_title);
        $post_title = htmlspecialchars_decode(trim($post_title[1]));
        $post_title = $wpdb->escape($post_title);

        preg_match('|<published>([^<]+)</published>|s', $data, $post_date);
        $post_date_gmt = gmdate('Y-m-d H:i:s', $post_date[1]);
        $post_date = date('Y-m-d H:i:s', $post_date[1]);

        preg_match('|<category>([^<]*)</category>|s', $data, $category);
        $category = $category[1];

        preg_match('|<visibility>([^<]+)</visibility>|s', $data, $visibility);
        $visibility = $visibility[1];
        $post_status = $status_map[$visibility];

        if ( $visibility == 'protected' ) {
            preg_match('|<password>([^<]+)</password>|s', $data, $post_password);
            $post_password = $post_password[1];
        } else {
            $post_password = '';
        }

        preg_match('|<content[^>]*>([^<]+)</content>|s', $data, $post_content);
        $post_content = htmlspecialchars_decode(trim($post_content[1]));
        $this->replacer_change($post_content);
        $post_content = $wpdb->escape($post_content);

        preg_match_all('|<tag>([^<]+)</tag>|s', $data, $tags_input);
        if ( is_array($tags_input[1]) ) {
            $tags_input = implode(',', $tags_input[1]);
            $tags_input = $wpdb->escape($tags_input);
        } else {
            $tags_input = '';
        }

        $post = compact(
            'post_type', 'post_author', 'post_date', 'post_date_gmt', 'post_content',
            'post_title', 'post_name', 'post_status', 'post_password', 'category', 'tags_input'
        );

        return $post;
    }

    function & parse_attachment() {
        // check valid position
        $pos = strpos($this->importdata, '<attachment ');
        if ( $pos !== 0 ) return -1;

        $attachment = '';

        // find <content> of '<attachment ' child
        while ( !feof($this->fp) || strlen($this->importdata) > $this->minimum_len ) {
            $content_pos = strpos($this->importdata, '<content>');
            $endattach_pos = strpos($this->importdata, '</attachment>');

            // no content
            if ( $endattach_pos !== false ) {
                if ( $content_pos === false || $content_pos > $endattach_pos ) {
                    $attachment .= substr($this->importdata, 0, $endattach_pos);
                    // +13 = '</attachment>' length
                    $this->importdata = substr($this->importdata, $endattach_pos+13);

                    $no_content = true;
                    break;
                }
            }

            if ( $content_pos !== false ) {
                $attachment .= substr($this->importdata, 0, $content_pos);
                // +9 = '<content>' length
                $this->importdata = substr($this->importdata, $content_pos+9);

                $no_content = false;
                break;
            } else {
                $offset = strlen($this->importdata) - $this->minimum_len;
                $attachment .= substr($this->importdata, 0, $offset);
                $this->importdata = substr($this->importdata, $offset);

                if ( !feof($this->fp) )
                    $this->importdata .= fread($this->fp, $this->read_len);
            }
        }


        // post info
        preg_match('|<attached>([^<]+)</attached>|s', $attachment, $post_date);
        $post_date_gmt = gmdate('Y-m-d H:i:s', $post_date[1]);
        $post_date = date('Y-m-d H:i:s', $post_date[1]);

        preg_match('|<label>([^<]+)</label>|s', $attachment, $post_title);
        $post_title = $post_title[1];

        preg_match('|<name>([^<]+)</name>|', $attachment, $post_name);
        $post_name = $post_name[1];

        preg_match('|mime="([^"]+)"|s', $attachment, $post_mime_type);
        $post_mime_type = $post_mime_type[1];

        $post = compact(
            'post_date', 'post_date_gmt', 'post_title',
            'post_name', 'post_mime_type'
        );


        if ( !$no_content ) {
            // file path
            $file_path = "{$this->attach_dir}/{$post_name}";
            if ( !$this->attach_overwrite )
                if ( file_exists($file_path) ) return 1;
            // open file in write mode
            $fp_attach = fopen($file_path, 'w+');
            if ( !$fp_attach ) return -2;

            // export file data
            while ( !feof($this->fp) || strlen($this->importdata) > $this->minimum_len ) {
                $pos = strpos($this->importdata, '</content>');

                if ( $pos !== false ) {
                    $data = substr($this->importdata, 0, $pos);
                    fwrite($fp_attach, base64_decode($data));
                    unset($data);
                    // +10 = '</content>' length
                    $this->importdata = substr($this->importdata, $pos+10);

                    break;
                } else {
                    $offset = strlen($this->importdata) - $this->minimum_len;
                    $offset -= $offset % 4; // for base64 decode
                    $data = substr($this->importdata, 0, $offset);
                    fwrite($fp_attach, base64_decode($data));
                    unset($data);
                    $this->importdata = substr($this->importdata, $offset);

                    if ( !feof($this->fp) )
                        $this->importdata .= fread($this->fp, $this->read_len);
                }
            }

            fclose($fp_attach);
        }

        return $post;
    }

    function & remove_logs_element($post) {
        $pos = strpos($post, '<logs>');
        if ( $pos === false ) return $post;
        $clean_post = substr($post, 0, $pos);

        $pos = strpos($post, '</logs>');
        // +7 = '</logs>' length
        $clean_post .= substr($post, $pos + 7);

        return $clean_post;
    }

    function parsing() {
        if ( !$this->fp ) return;

        set_time_limit(0);

        while ( !feof($this->fp) || strlen($this->importdata) > $this->minimum_len ) {
            // init variable
            $post = '';
            $attachments = array();

            ### find '<(post|notice) '
            while ( !feof($this->fp) || strlen($this->importdata) > $this->minimum_len ) {
                if ( !feof($this->fp) )
                    $this->importdata .= fread($this->fp, $this->read_len);

                preg_match('/<(post|notice) /', $this->importdata, $matches, PREG_OFFSET_CAPTURE);

                if ( !empty($matches) ) {
                    $post_pos = $matches[0][1];
                    $post_tag = $matches[1][0];
                    $this->importdata = substr($this->importdata, $post_pos);
                    break;
                } else {
                    $this->importdata = substr($this->importdata, -$this->minimum_len);
                }
            }

            // not found? '<(post|notice) '
            if ( empty($matches) )
                break;
            else
                unset($matches);


            ### find '<attachment ' or '</(post|notice)>'
            while ( !feof($this->fp) || strlen($this->importdata) > $this->minimum_len ) {
                $attach_pos = strpos($this->importdata, '<attachment ');
                $endpost_pos = strpos($this->importdata, "</{$post_tag}>");

                if ( $attach_pos === false && $endpost_pos === false ) {
                    $offset = $attach_pos - $this->minimum_len;
                    $post .= substr($this->importdata, 0, $offset);
                    $this->importdata = substr($this->importdata, $offset);

                    if ( !feof($this->fp) )
                        $this->importdata .= fread($this->fp, $this->read_len);

                    continue;
                }

                ## '<attachment ' inner '</(post|notice)>'
                if ( $attach_pos !== false ) {
                    if ( $endpost_pos === false || $attach_pos < $endpost_pos ) {
                        $post .= substr($this->importdata, 0, $attach_pos);
                        $this->importdata = substr($this->importdata, $attach_pos);

                        $attachments[] = &$this->parse_attachment();
                        continue;
                    }
                }

                ## find '</(post|notice)>'
                if ( $endpost_pos !== false ) {
                    // +3 = '</' and '>' length
                    $offset = $endpost_pos + strlen($post_tag) + 3;
                    $post .= substr($this->importdata, 0, $offset);
                    $this->importdata = substr($this->importdata, $offset);

                    // remove logs element in post data
                    $post = &$this->remove_logs_element($post);

                    # parsing data
                    $comments = &$this->parse_comments($post);
                    $trackbacks = &$this->parse_trackbacks($post);
                    $post = &$this->parse_post($post);


                    # import data
                    $title = htmlspecialchars($post['post_title']);
                    $post_id = $this->import_post($post);
                    if ( $post_id > 0 ) {
                        $this->import_comments($post_id, $comments);
                        $this->import_comments($post_id, $trackbacks);
                        $this->import_attachments($post_id, $attachments);

                        // count info
                        if ( $post['tags_input'] !== '' ) {
                            $tag_count = count(explode(',', $post['tags_input']));
                        } else {
                            $tag_count = 0;
                        }

                        $comment_count = count($comments);
                        foreach ( $comments as $cmt )
                            $comment_count += count($cmt['replys']);

                        $trackback_count = count($trackbacks);
                    }

                    unset($post);
                    unset($comments);
                    unset($attachments);


                    # show result
                    if ( $post_id > 0 ) {
                        ++$this->import_count;
                        $this->message("Import({$this->import_count}): {$title} *Tags({$tag_count}) *Comments({$comment_count}) *Trackbacks({$trackback_count})");
                    } else if ( $post_id === 0 ) {
                        ++$this->already_count;
                        $this->message("Already({$this->already_count}): {$title}");
                    } else {
                        $this->message("Error({$post_id}): {$title}");
                        exit;
                    }

                    // one post completed and next
                    break;
                } // end if
            }
        }

        fclose($this->fp);
    }

    function import() {
        $dir = wp_upload_dir();

        // attach file directory check
        if ( !is_dir($this->attach_dir) ) {
            if ( !mkdir($this->attach_dir, 0755) ) {
                echo $this->attach_dir.' '.__('create directory error.');
                return;
            }
        }

        // backup file cehck
        if ( $_POST['filepath'] ) {
            $url = parse_url($_POST['filepath']);

            if ( !$url['scheme'] ) {
                $this->fp = @fopen($url['path'], 'r');
                if ( !$this->fp ) {
                    echo "'{$url['path']}' " . __('file is not readable.');
                    return;
                }
            } else if ( $url['scheme'] == 'http' ) {
                $this->fp = @fsockopen($url['host'], 80, $errno, $error);
                if ( !$this->fp ) {
                    echo "{$errno}, {$error}";
                    return;
                }

                $out = "GET {$url['path']} HTTP/1.1\r\n";
                $out .= "Host: {$url['host']}\r\n";
                $out .= "Connection: close\r\n\r\n";
                fwrite($this->fp, $out);
            } else {
                echo sprintf(__("ttxml-importer does not support '%s'"), $url['scheme']);
                return;
            }
        } else {
            check_admin_referer('import-upload');
            $file = wp_import_handle_upload();
            if ( isset($file['error']) ) {
                echo $file['error'];
                return;
            }

            $this->fp = @fopen($file['file'], 'r');
            if ( !$this->fp ) {
                echo "'{$file['file']}' " . __('file is not readable.');
                return;
            }
        }

        echo '<p>';
        echo __('Attach files dir').": {$this->attach_dir}";
        echo '</p>';
        flush();

        $this->parsing();
        if ( $file['id'] )
            wp_import_cleanup($file['id']);
        do_action('import_done', 'ttxml');

        echo '<h3>';
        printf(__('All done. <a href="%s">Have fun!</a>'), get_option('home'));
        echo '</h3>';
    }

    function dispatch() {
        settype($_GET['step'], 'int');
        $step = $_GET['step'];

        $this->header();

        switch ($step) {
            case 0 :
                $this->greet();
                break;
            case 1 :
                $result = $this->import();
                if ( is_wp_error($result) )
                    echo $result->get_error_message();
                break;
        }

        $this->footer();
    }

    function TTXML_Import() {
        $this->__construct();
    }

    function __construct() {
        $dir = wp_upload_dir();
        $this->attach_dir = preg_replace('|/$|', '', $dir['basedir']).'/'.$this->attach_subdir;
        $this->attach_url = preg_replace('|/$|', '', $dir['baseurl']).'/'.$this->attach_subdir;
    }
}

$ttxml_import = new TTXML_Import();

register_importer('ttxml', __('TTXML'), __('Import posts from TTXML.'), array($ttxml_import, 'dispatch'));
?>
