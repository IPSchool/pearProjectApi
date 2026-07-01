<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!function_exists('importExcel')) {
    function importExcel(string $file = '', int $sheet = 0, int $columnCnt = 0, &$options = [])
    {
        try {
            $file = iconv('utf-8', 'gb2312', $file);
            if (empty($file) || !file_exists($file)) {
                throw new \Exception('文件不存在!');
            }

            $objRead = IOFactory::createReader('Xlsx');
            if (!$objRead->canRead($file)) {
                $objRead = IOFactory::createReader('Xls');
                if (!$objRead->canRead($file)) {
                    throw new \Exception('只支持导入Excel文件！');
                }
            }

            empty($options) && $objRead->setReadDataOnly(true);
            $obj = $objRead->load($file);
            $currSheet = $obj->getSheet($sheet);

            if (isset($options['mergeCells'])) {
                $options['mergeCells'] = $currSheet->getMergeCells();
            }

            if (0 == $columnCnt) {
                $columnH = $currSheet->getHighestColumn();
                $columnCnt = Coordinate::columnIndexFromString($columnH);
            }

            $rowCnt = $currSheet->getHighestRow();
            $data = [];

            for ($_row = 1; $_row <= $rowCnt; $_row++) {
                $isNull = true;
                $format = null;
                for ($_column = 1; $_column <= $columnCnt; $_column++) {
                    $cellName = Coordinate::stringFromColumnIndex($_column);
                    $cellId = $cellName . $_row;
                    $cell = $currSheet->getCell($cellId);

                    if (isset($options['format'])) {
                        $format = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        $options['format'][$_row][$cellName] = $format;
                    }

                    if (isset($options['formula'])) {
                        $formula = $currSheet->getCell($cellId)->getValue();
                        if (0 === strpos((string) $formula, '=')) {
                            $options['formula'][$cellName . $_row] = $formula;
                        }
                    }

                    if (isset($format) && 'm/d/yyyy' == $format) {
                        $cell->getStyle()->getNumberFormat()->setFormatCode('yyyy/mm/dd');
                    }

                    $data[$_row][$cellName] = trim((string) $currSheet->getCell($cellId)->getFormattedValue());
                    if (!empty($data[$_row][$cellName])) {
                        $isNull = false;
                    }
                }
                if ($isNull) {
                    unset($data[$_row]);
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
