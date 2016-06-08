##TTXML Importer
* Contributors: MinKwon Park
* Donate link: http://ani2life.com/
* Tags: importer, ttxml, textcube, tistory
* Requires at least: 3.0
* Tested up to: 3.7
* Stable tag: 2.5

TTXML(티스토리/텍스트큐브 백업파일)의 내용을 워드프레스로 가져오는 Importer프로그램 입니다. 


### Installation & Usages
1. Upload the `ttxml-importer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Tools -> Import screen, Click on TTXML
4. (For Tistory User) If you want to get numeric permalink of Tistory id.tistory.com/1, please leave checked. Otherwise, such as id.tistory.com/post-title, please uncheck.

1. `ttxml-importer` 폴더를 `/wp-content/plugins/` 디렉토리에 업로드 합니다.
2. 워드프레스의 플러그인 메뉴에서 플러그인을 활성화 시킵니다.
3. 도구 -> Import 화면에서 TTXML을 선택합니다.
4. (티스토리 사용자) 만약 Tistory의 숫자 방식 URL을 그대로 두시려면, 체크박스를 그대로 두시면 됩니다. 제목 기반의 URL 쓰신다면, 체크박스를 해제하세요.

###Frequently Asked Questions
Q: 용량이 큰 TTXML 파일도 가능한가요? =
자신의 워드프레스 호스팅에 FTP 등으로 직접 업로드하여 복구하는 방식으로 가능합니다.
 
###Changelog ==
####2.6 
* Tistory 사용자를 위한 Permalink 선택 방식 추가
####2.5
* 미디어 라이브러리 및 대표이미지 선택시 섬네일 관련 문제 해결
* 첫번째 첨부 이미지를 대표이미지로 선택
####2.4
* 워드프레스 플러그인 방식으로 제공
