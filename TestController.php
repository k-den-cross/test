<?php

namespace App\Http\Controllers;

use App\Exceptions\RatioBuildException;
use App\Jobs\BuildAnkFileJob;
use App\Mail\RatioMail;
use App\Services\File\LabelSheetServices;
use App\Services\File\RawDataSheetServices;
use App\Services\File\GtSheetServices;
use Illuminate\Http\Request;

use App\Models\DB10\PrjInfo;
use App\Models\DB10\EqtInfo;
use App\Models\DB10\QtpQuotaTable;
use App\Models\DB20\RsAnsData;
use App\Models\DB20\RsAttribute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Models\RatioInfo;
use App\Services\DataRestructureServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ZipArchive;
class TestController extends Controller
{
    public function __construct(LabelSheetServices $labelSheet,RawDataSheetServices $rawDataSheet, GtSheetServices $gtSheet)
    {
    }

    public function index(Request $request){
        Mail::to('k_den@cm-group.co.jp')->send(new RatioMail( 12, 'test'));
    }
    public function index2(){
        try{
            $this->ank_id = 100029;

            $EqtInfo = resolve(EqtInfo::class);
            $parts_no = array_column($EqtInfo->getByAnkIdAndPartNo($this->ank_id)->toArray(), 'nxs_enquete_no');
            $final_part_no = array_pop($parts_no);

            $dataRestructure = resolve(DataRestructureServices::class);

            $allQuestion = $dataRestructure->getColList($this->ank_id, $parts_no);

            $codeConvertor = resolve('CodeConvertorUtils', $allQuestion);

            $RsAttribute = resolve(RsAttribute::class);

            $list = [
                '([s1]=1)and([sc1]=1or( [sc2_1]<16  or[s2]=1) )',

                '([s1]=2)and([s2]=1)',

                '([s1]=3)and([sc2_1]>30)',

                '([s1]=4)and([sc2_1]!=12&:15or[s2]=2)',

                '([s1]=5)and([sc2_1] !=15&:18or[s2]=2)',

                '([s1]=6) and ([sc2_1]  != 15&:20 or [s2] = 2)',

                '([s1]=7)and([sc2_1] !=15&:30)',

                '([s1]=8)and([sc2_1] !=18&:30)',

                '([s1]=9)and([sc2_1]!=15&:30)',

                '([s1]=10and[sc2_1]<15)',

                '[s2]=2and(([sc1]=1and[sc2_1]<18)or([sc1]=2and[sc2_1]<16))',

                '([sc1]=1and([sc2_1]-[s5_1])<18)or([sc1]=2and([sc2_1]-[s5_1])<16)',

                '([s1]=1)and([sc1]!=2:4)',

                '([s3]=1)and((([sc1]=1)and[sc2_1]<18)or([sc1]=2and[sc2_1]<16))',

                '(count([s7])+count([s8]))>10',

                '([s6_1]>10)',

                'count([s7])>10',

                '(count([s7]) + count([s8])) >10',

                '([s9] + count([s7])) >10',

                '([s6_1] + count([s7])) >10',

                '([s7]=1)and[s2]=1',

                '([sc1]=1and[s10_1]<16)or([sc1]=2and[s10_1]<18)',

                '([s11]=3)and([s10_1]>30)',

                '([s12]=1and[sc2_1]<15)',

                '([s12]=2and[sc2_1]<15)',

                '([s12]=3and[sc2_1]<15)',

                '([s12]=4and[sc2_1]<18)',

                '([s12]=5and[sc2_1]<18)',

                '([s12]=6and[sc2_1]<22)',

                '([s12]=7and[sc2_1]<24)',

                '([s12]=8and[sc2_1]<18)',

                '([s12]=9and[sc2_1]<20)',

                '([s12]=9and[sc2_1]<20)',

                '([s12]=11and[sc2_1]<20)',

                '([s12]=12and[sc2_1]<22)',

                '([s12]=13and[sc2_1]<24)',

                '([s12]=14and[sc2_1]<27)',

                '([sc2_1]-[s13_1])<15',

                '[s14]=1and(([sc1]=1and([sc2_1]<18))or([sc1]=2and[sc2_1]<16))',

                '[s15_1]<[s16_1]',

                'count([s17])>4',

                '([s18_1]>4)',

                '([sc3]!=[rfs4])and([rfs4]!=null)',

                '([sc4]=10)and([rfs4]!=30)',

                '([sc4]=11and[rfs5]!=4)',

                '([other_data01]=1)',

                '([other_data01]!=null)',

                // '([q1]=1)',

                // '(count([q1])>3)',

                '([s{1:3}]=1)and([sc1]=1)',

                '([s{1,2}]=1)and([sc1]=1and[sc2_1]<34)',

                '[s{1,2}]=1 and ([sc1]=1 and [sc2_1]<34)',

                '([s1]=9 )and ([sc2_1]  = 15:30)',

                '([s1]<[s2])and([sc2_1]=1:18)',

                '([sc4]=7,18)and([s1]=1and[sc2_1]<24or([s1]=2and[sc2_1]<22))',

                'count([s7]) = 1',

                'not ([s1] = 1) ',

                '[s{1&11}] = 1:6',

                '[s{1,11}] != 1&6',

                '([s{7,17}] = 1&3&5)',

                '[s{7&17}] = 1,3,5',

                '[s13_1] != 1&:5',

                '[sc19_1_{1&3}] != 1:5',

                '[other_data01]!=1&3',

                '[other_data0{1&3}] = 1,3,5',

                '([sc20_{1&:3}] = 1:5)',

                '[sc20_{1,3}] != 1&:3',

                '[sc21_{1&:3}] = 1,2,3',

                '[sc21_{1,3}] = 1&:3',

                '[sc19_1_{1&:3}] != 1:5',

                '[sc19_1_{1,3}] != 1&:5',

                '[other_data0{1&:3}]!=1,3,5',

                '[other_data0{1:3}]!=1&:3',

                '[sc21_{1,3}] = 1&2'
            ];

            $list = ['sc1 = 1'];
            //一時テーブルを作成
            $createColumn = [];
            foreach($allQuestion as  $question){
                switch($question['type']){
                    case 'SA':
                    case 'MA':
                        $createColumn[] = $question['qCol'] . ' character varying(1)';

                        foreach($question['categories'] as $category){
                            foreach($category['otherFa'] as $otherFa){
                                $colname = $question['qCol'] . '_snt' . $category['catNo'] . '_' . $otherFa['othersort'];
                                $createColumn[] = $colname . ' character varying(1)';
                            }
                        }
                        break;
                    case 'FA':
                        foreach($question['categories'] as $category){
                            $colname = $question['qCol'] . '_' . $category['catNo'];
                            $createColumn[] = $colname . ' character varying(1)';
                        }
                        break;
                    case 'NU':
                        foreach($question['categories'] as $category){
                            $colname = $question['qCol'] . '_' . $category['catNo'];
                            $createColumn[] = $colname . ' character varying(1)';
                        }
                        break;
                    default:
                        $createColumn[] = $question['qCol'] . ' character varying(1)';
                }
            }


            $tableName = 'sc_data_condition_check_'. $this->ank_id;
            DB::statement('CREATE TEMPORARY TABLE '. $tableName .' ('. implode(',', $createColumn).')');


            foreach($list as $condition){

                $sql_where = $codeConvertor->convert($condition);

                if($sql_where === false){
                    $message = [trans("ratio.conditionErrorDefault")];
                    if( !empty($codeConvertor->errMessage) ) $message += array_merge($message, $codeConvertor->errMessage);
                    $this->message = implode('<br>', $message);

                    dump($this->message);
                    return 'false';
                }

                echo  $sql_where .'<br>';

                // DB::statement('SELECT 1 FROM  '. $tableName .' where '. $sql_where);

                // $RsAttribute->getScDataWithAns($this->ank_id, $parts_no, $final_part_no, $sql_where, 1);

            }
            return 'true';
        }
        catch(\Exception $e){
            $this->message = trans("ratio.conditionErrorUnknow", [$condition, $sql_where]);
            Log::error($this->message);
            Log::error($e);

            return 'false';
        }
    }

    public function index3(Request $request){

        // $ank_id = 354550;
        $ank_id = 100029;

        $EqtInfo = resolve(EqtInfo::class);

        $parts_no = array_column($EqtInfo->getByAnkIdAndPartNo($ank_id)->toArray(), 'nxs_enquete_no');
        if(empty($parts_no)){
            abort(404);
        }

        $Spreadsheet = resolve(Spreadsheet::class);

        $RatioInfo = resolve(RatioInfo::class);

        $ratio_info = $RatioInfo->getByJobNoOrAnkId( null, 100029 )[0]??[];
        if(empty($ratio_info)) abort(404);

        $ratio_option = json_decode($ratio_info['option'], true);

        //collect data
        $dataRestructure = resolve(DataRestructureServices::class);
        $allQuestion = $dataRestructure->getColList($ank_id, $parts_no);

        $qColQuestionMap = $dataRestructure->createQcolQuestionMap($allQuestion, $ratio_option);

        $RsAttribute = resolve(RsAttribute::class);
        $ansDataList = $RsAttribute->getDataWithAns($ank_id, $parts_no);
        $ansDataList = $dataRestructure->filterAnsData(json_decode($ratio_option['col_filter'], true), $ratio_option['filter_type'], $ansDataList);

        //ラベル対応表
        if(in_array("1",$ratio_option['export_files'])){
            $labelSheet = resolve(LabelSheetServices::class);
            $labelSheet->buildSheet($qColQuestionMap);
            $Spreadsheet->addExternalSheet($labelSheet->getBuildSheet());
        }

        //ローデータ
        if(in_array("2",$ratio_option['export_files'])){
            $rawDataSheet = resolve(RawDataSheetServices::class);
            $rawDataSheet->buildSheet($qColQuestionMap, $ansDataList, $ratio_option);
            foreach($rawDataSheet->getBuildSheets() as $sheet){
                $Spreadsheet->addExternalSheet($sheet);
            }
        }

        if(in_array("3",$ratio_option['export_files'])){
            $gtSheet = resolve(GtSheetServices::class);
            $gtSheet->buildSheet($qColQuestionMap, $ansDataList);
            $Spreadsheet->addExternalSheet($gtSheet->getBuildSheet());
        }


        //SCデータ生成
        if($ratio_option['sc_data'] != '1' && count($parts_no) != 1){
            $sc_parts_no = $parts_no;
            $final_part_no = array_pop($sc_parts_no);
            $sc_data_num = $ratio_option['sc_data'] == '3' ? $ratio_option['sc_data_num'] : null;

            $allQuestion = $dataRestructure->getColList($ank_id, $sc_parts_no);
            $qColQuestionMap = $dataRestructure->createQcolQuestionMap($allQuestion, $ratio_option);

            $scAnsDataList = $RsAttribute->getScDataWithAns($ank_id, $sc_parts_no, $final_part_no, $sc_data_num);

            $scRawDataSheet = resolve(RawDataSheetServices::class);
            $scRawDataSheet->buildSheet($qColQuestionMap, $scAnsDataList, $ratio_option);
            foreach($scRawDataSheet->getBuildSheets() as $sheet){
                $sheet->setTitle( Config::get('common.SC_SHEET_PREFIX') . $sheet->getTitle() );
                $Spreadsheet->addExternalSheet($sheet);
            }
        }

        if($Spreadsheet->getSheetCount() != 1){
            $Spreadsheet->removeSheetByIndex(0);
            $writer = new Xlsx($Spreadsheet);

            $path = '/xlsx/'. $ank_id . '.xlsx';
            $writer->save(getcwd() . $path);

            echo 'http://' . $request->getHttpHost() . $path;
        }


    }





}


