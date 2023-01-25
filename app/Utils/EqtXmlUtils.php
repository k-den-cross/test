<?php

namespace App\Utils;

use SimpleXMLElement;

class EqtXmlUtils
{
    	// 定数定義　：　設定系
	const MAX_COLUMN_COUNT   = 300;					// 1Tableに含める最大カラム数
	const MAX_ANSTABLE_COUNT = 20;					// 回答Tableの最大数
	const MAX_FA_LENGTH    = 1000;					// FA最大文字数
	const MAX_NU_VALUE     = 999999;				// NU回答 最大値
	const MIN_NU_VALUE     = -999999;				// NU回答 最小値
	const MSG_MUSTANSWER   = '回答されていません。';	// 無回答時のエラーメッセージ

	// dataファイル容量 規定オーバー系
	const BASE_ALERT_FILE_SIZE = 1048576;	// Byte(設定値は2MB)
	private $overSizeFiles = array();

	// DB
	private $pyxis2_db = NULL;	// Pyxis2管理画面のDB（ アンケート名等の情報や設定の取得に使用 ）

	// 読み込むXMLデータ。SimpleXMLオブジェクト形式。
	private $xml_strc  = NULL;	// 構成情報
	private $xml_logic = NULL;	// ロジック情報

	// コードコンバータ
	var	$codeConv = NULL;

	// アンケートID
	private $eqtID = NULL;

	// パートNo
	private $partNo = NULL;

	// アンケート名
	private $eqtName = NULL;

	// 案件の種別 （ 0: 通常 / 1:ASP / 2:誘導 ）
	private $eqtType = NULL;

	// 「」、『』の色
	private $parentheses_color = NULL;
	private $dbl_parentheses_color = NULL;

	// 英字半角化
	private $char_conversion_flg = false;

	// 英字半角化実施有無
	private $is_char_conversion = true;

	// FA デフォルト文字数
	private $single_fa_max_num = NULL;
	private $multi_fa_max_num = NULL;
	/* modify ns akamatsu 2011/3/17 No.250  */
	private $single_fa_min_num = NULL;
	private $multi_fa_min_num = NULL;
	/* modify ns akamatsu 2011/3/17 No.250  */

	// デザイン名（ 選択されたデザインテンプレートのフォルダ名 ）
	private $design_name = NULL;

	// 設問番号List
	private $qNoList      = array();			// 設問番号のリスト
	private $eidToQNoList = array();			// XMLのelementIDから見た設問番号の対応リスト
	private $itemAddeidToQNoList = array();		// アイテム作成から見た、アイテム名の対応リスト

	// ページのHTMLメッセージリスト（structureの定義から各ページへのメッセージ渡しに使用）
	private $pageHtmlMessageList = array();

	// [& ] で設定されたラベル出力設問番号リスト
	private $textQNoList = array();

	// カテゴリテキストの全データ（SC + 本調査等で使用するのでここのレイヤーに配置）
	private $categoryTextAllData = array();

	// 設問番号毎のカテゴリ数（structure.inc内に付帯FAの数を埋め込む為使用）
	private $catecoryCountAllData = array();

	// 生成失敗時のエラーメッセージ
	private $errMessage = '';

	// elementID用シーケンス
	private $elementIdSeq = 0;

	// 設問グループ用シーケンス
	private $qgIdSeq = 0;

    public function __construct($structure, $logic)
    {
        $this->xml_strc = simplexml_load_string($structure);
        $this->xml_logic = simplexml_load_string($logic);


    }

    public function getAllQuestion(): array{
        $questionList = [];
		foreach( $this->xml_strc->structure[0] as $item ) {
			switch( $item->getName() ) {
				case 'question':
                    $questionList[] = $this->analysisQuestionXmltoArr($item);
                    break;
				case 'qgroup':
                    foreach($item->question as $sub_time){
                        $question = $this->analysisQuestionXmltoArr($sub_time);
                        $question['name']  = $item->property->name . '／' . $question['name'];
                        // $question['content']  = $this->changeQContent( trim( $item->property->content )) . '／' . $question['content'];
                        $questionList[] = $question;
                    }
                    break;
			}
		}

        return $questionList;
    }

    protected function analysisQuestionXmltoArr(SimpleXMLElement $question): array{
        //TODO 補足？
        return
        [
            'qCol' => $question->property->qCol->__toString(),
            'qNo' => $question->property->qNo->__toString(),
            'name' => $question->property->name->__toString(),
            // 'content' => $this->changeQContent( trim( $question->property->content )),
            'type' => $question->property->type->__toString(),
            'categories' => $this->getAllCategories($question->categories->category )
        ];
    }

    public function getAllCategories(SimpleXMLElement $categories): array{
        $categoryList = [];
        foreach($categories as $item){
            if($item->groupid == ''){
                $id = $item->attributes()->id->__toString();
                $categoryList[ $id ] = $this->analysiCategoryXmltoArr($item);
            }
            //その他FA（ループ中必ず所属カテの後ろと想定）
            else{
                $otherFa = $this->analysiOtherFaXmltoArr($item);
                $categoryList[ $otherFa['groupid'] ]['otherFa'][] = $otherFa;
            }
        }

        return array_values($categoryList);
    }

    protected function analysiCategoryXmltoArr(SimpleXMLElement $category): array{
        //TODO 補足？
        return
        [
            'catNo' => $category->catNo->__toString(),
            'value' => $category->value->__toString(),
            'name' => $category->name->__toString(),
            // 'content' => $this->changeCContent( trim( $category->content )),
            'otherFa' => []
        ];
    }

    protected function analysiOtherFaXmltoArr(SimpleXMLElement $otherFa): array{
        //TODO 補足？
        return
        [
            'groupid' =>   $otherFa->groupid->__toString(),
            'othertype' => $otherFa->othertype->__toString(),
            'othersort' => $otherFa->othersort->__toString()
        ];
    }

    /**
	 * 設問文、設問注釈文、オブジェクトのテキスト　用
	 *
	 */
	protected function changeQContent( $str, $clickMustFlag = NULL, $isChange = true ) {

		//[command][/command]を削除
		$str = preg_replace( '/\[(?:|\/)command\]/', '', $str );

		// 全半角変換を実行するか、否か
		if ( $this->is_char_conversion && $isChange ) {

			// 英字全角・半角化
			if ( $this->char_conversion_flg ) {

				// タグを除いて半角化
				preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
				$cnt_tags = count( $tags[ 0 ] );
				$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
				$cnt_txts = count( $txts );

				$str = '';
				for ( $i = 0; $i < $cnt_txts; ++$i ) {

					$ntxt = $txts[ $i ];
					$ntxt = preg_replace( '/＜/', '&lt;', $ntxt );
					$ntxt = preg_replace( '/＞/', '&gt;', $ntxt );

					$ntxt = str_replace( '”', '&quot;', $ntxt );
					$ntxt = str_replace( '"', '&quot;', $ntxt );
					// 「～」は全角化はするが半角化はしない。（年齢の20歳～30歳とかを半角化するとおかしくなるから）
					//$ntxt = str_replace( "～", "~", $ntxt );
					$ntxt = str_replace( "￣", "~", $ntxt );
					$ntxt = str_replace( '￥', "\\", $ntxt );

					$str .= mb_convert_kana( $ntxt, 'rnas' );
					if ( $cnt_tags > $i ) {
						$str .= $tags[ 0 ][ $i ];
					}
				}

			} else {

				// タグを除いて全角化
				preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
				$cnt_tags = count( $tags[ 0 ] );
				$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
				$cnt_txts = count( $txts );

				$str = '';
				for ( $i = 0; $i < $cnt_txts; ++$i ) {

					// エスケープされているHTMLエンティティを復元してから全角化
					$ntxt = $txts[ $i ];
					$ntxt = preg_replace( '/&lt;/',   '<', $ntxt );
					$ntxt = preg_replace( '/&gt;/',   '>', $ntxt );
					$ntxt = preg_replace( '/&nbsp;/', ' ', $ntxt );
					$ntxt = preg_replace( '/&quot;/',  '"', $ntxt );

					// &amp;の復元は、他のエンティティの後でやらないと誤認されるので注意
					$ntxt = preg_replace( '/&amp;/',  '&', $ntxt );

					$ntxt = str_replace( '"', '”', $ntxt );
					$ntxt = str_replace( "~", "～", $ntxt );
					$ntxt = str_replace( '\\', "￥", $ntxt );

					$str .= mb_convert_kana( $ntxt, 'RNASKV' );
					if ( $cnt_tags > $i ) {
						$str .= $tags[ 0 ][ $i ];
					}
				}
			}

		} else {

			preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
			$cnt_tags = count( $tags[ 0 ] );
			$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
			$cnt_txts = count( $txts );

			$str = '';
			for ( $i = 0; $i < $cnt_txts; ++$i ) {

				$ntxt = $txts[ $i ];

				// 半角文字のエンティティ化だけ実施
				$ntxt = preg_replace( '/</', '&lt;', $ntxt );
				$ntxt = preg_replace( '/>/', '&gt;', $ntxt );
				//$ntxt = preg_replace( '/ /', '&nbsp;', $ntxt );
				$ntxt = preg_replace( '/"/', '&quot;', $ntxt );

				$str .= $ntxt;
				if ( $cnt_tags > $i ) {
					$str .= $tags[ 0 ][ $i ];
				}

			}

		}

		// エスケープ処理
		$str = str_replace( "\\", "\\\\", $str );
		$str = mb_ereg_replace( '\"', '\\"', $str );

		// Click必須のonClickイベント置換
		if ( $clickMustFlag ) {
			$str = preg_replace( '/__PX2_CLICKED_FLAG__/', $clickMustFlag . '=true;', $str );
		}

		// 設問回答の埋め込み
		$str = preg_replace( "/\[([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/", '" . htmlspecialchars( GetData( strtolower( "\\1" ) ) ) . "', $str );
		while( preg_match( "/\[(\&|\&amp;)([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/", $str, $pregResult ) == 1 ) {
			if ( !in_array( strtolower( $pregResult[ 2 ] ), $this->textQNoList ) ) {
				$this->textQNoList[] = strtolower( $pregResult[ 2 ] );
			}
			$str = preg_replace( '/\[(\&|\&amp;)([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/', "\" . GetAnsText( strtolower( \"\\2\" ) ) . \"", $str, 1 );
		}

		// 「」、『』の色
		if ( $this->parentheses_color !== '#000000' ) {
			$str = mb_ereg_replace( "「([^」]*)」", "<font color=\\\"" . $this->parentheses_color . "\\\">「\\1」</font>", $str );
		}
		if ( $this->dbl_parentheses_color !== '#000000' ) {
			$str = mb_ereg_replace( "『([^』]*)』", "<font color=\\\"" . $this->dbl_parentheses_color . "\\\">『\\1』</font>", $str );
		}

		return $str;

	}

    /**
	 * カテゴリー、その他のテキスト　用
	 *
	 */
	protected function changeCContent( $str, $isChange = true ) {

		//[command][/command]を削除
		$str = preg_replace( '/\[(?:|\/)command\]/', '', $str );

		// 全半角変換を実行するか、否か
		if ( $this->is_char_conversion && $isChange ) {

			// 英字全角・半角化
			if ( $this->char_conversion_flg ) {

				// タグを除いて半角化
				preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
				$cnt_tags = count( $tags[ 0 ] );
				$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
				$cnt_txts = count( $txts );

				$str = '';
				for ( $i = 0; $i < $cnt_txts; ++$i ) {

					$ntxt = $txts[ $i ];
					$ntxt = preg_replace( '/＜/', '&lt;', $ntxt );
					$ntxt = preg_replace( '/＞/', '&gt;', $ntxt );

					$ntxt = str_replace( '”', '&quot;', $ntxt );
					$ntxt = str_replace( '"', '&quot;', $ntxt );
					// 「～」は全角化はするが半角化はしない。（年齢の20歳～30歳とかを半角化するとおかしくなるから）
					//$ntxt = str_replace( "～", "~", $ntxt );
					$ntxt = str_replace( "￣", "~", $ntxt );
					$ntxt = str_replace( '￥', "\\", $ntxt );

					$str .= mb_convert_kana( $ntxt, 'rnas' );
					if ( $cnt_tags > $i ) {
						$str .= $tags[ 0 ][ $i ];
					}
				}

			} else {
				// タグを除いて全角化
				preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
				$cnt_tags = count( $tags[ 0 ] );
				$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
				$cnt_txts = count( $txts );

				$str = '';
				for ( $i = 0; $i < $cnt_txts; ++$i ) {

					// エスケープされているHTMLエンティティを復元してから全角化
					$ntxt = $txts[ $i ];
					$ntxt = preg_replace( '/&lt;/',   '<', $ntxt );
					$ntxt = preg_replace( '/&gt;/',   '>', $ntxt );
					$ntxt = preg_replace( '/&nbsp;/', ' ', $ntxt );
					$ntxt = preg_replace( '/&quot;/',  '"', $ntxt );

					// &amp;の復元は、他のエンティティの後でやらないと誤認されるので注意
					$ntxt = preg_replace( '/&amp;/',  '&', $ntxt );

					$ntxt = str_replace( '"', '”', $ntxt );
					$ntxt = str_replace( "~", "～", $ntxt );
					$ntxt = str_replace( '\\', "￥", $ntxt );

					$str .= mb_convert_kana( $ntxt, 'RNASKV' );
					if ( $cnt_tags > $i ) {
						$str .= $tags[ 0 ][ $i ];
					}
				}
			}

		} else {

			preg_match_all( '/(<[^>]+>|\[[^]]+\])/', $str, $tags );
			$cnt_tags = count( $tags[ 0 ] );
			$txts = preg_split( "/(<[^>]+>|\[[^]]+\])/", $str );
			$cnt_txts = count( $txts );

			$str = '';
			for ( $i = 0; $i < $cnt_txts; ++$i ) {

				$ntxt = $txts[ $i ];

				// 半角文字のエンティティ化だけ実施
				$ntxt = preg_replace( '/</', '&lt;', $ntxt );
				$ntxt = preg_replace( '/>/', '&gt;', $ntxt );
				//$ntxt = preg_replace( '/ /', '&nbsp;', $ntxt );
				$ntxt = preg_replace( '/"/', '&quot;', $ntxt );

				$str .= $ntxt;
				if ( $cnt_tags > $i ) {
					$str .= $tags[ 0 ][ $i ];
				}

			}

		}


		// エスケープ処理
		$str = str_replace( "\\", "\\\\", $str );
		$str = mb_ereg_replace( '\"', '\\"', $str );

		// 設問回答の埋め込み
		$str = preg_replace( "/\[([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/", "\" . htmlspecialchars( GetData( strtolower( \"\\1\" ) ) ) . \"", $str );
		while( preg_match( "/\[(\&|\&amp;)([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/", $str, $pregResult ) == 1 ) {
			if ( !in_array( strtolower( $pregResult[ 2 ] ), $this->textQNoList ) ) {
				$this->textQNoList[] = strtolower( $pregResult[ 2 ] );
			}
			$str = preg_replace( '/\[(\&|\&amp;)([a-zA-Z]+[a-zA-Z0-9_]*[a-zA-Z0-9]?)\]/', "\" . GetAnsText( strtolower( \"\\2\" ) ) . \"", $str, 1 );
		}

		return $str;
	}
}


