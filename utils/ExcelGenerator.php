<?php

class ExcelGenerator {
    
    public static function generateExcel($data, $filename, $sheetName = 'Hoja1', $columnWidths = null) {
        if (empty($data)) {
            throw new Exception('No hay datos para exportar');
        }

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'xlsx_');
        
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión ZIP de PHP no está habilitada. Habilítela en php.ini (extension=zip) y reinicie Apache.');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($tmpXlsx, ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            throw new Exception('No se pudo crear el archivo Excel. Código de error: ' . $result);
        }

        $colLetter = function($i){
            $i = (int)$i; 
            $letters = '';
            while ($i >= 0) { 
                $letters = chr($i % 26 + 65) . $letters; 
                $i = intdiv($i, 26) - 1; 
            }
            return $letters;
        };
        
        $xmlHeader = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>";

        // Content Types
        $contentTypes = $xmlHeader.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // Main relationships
        $rels = $xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // Workbook
        $safeSheetName = htmlspecialchars($sheetName, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $workbook = $xmlHeader.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$safeSheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // Workbook relationships
        $wbRels = $xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // Shared Strings (vacío pero requerido)
        $sharedStrings = $xmlHeader.'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"></sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);

        // Styles (mínimo requerido)
        $styles = $xmlHeader.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // Column widths
        $colsXml = '';
        if ($columnWidths && is_array($columnWidths)) {
            $colsXml = '<cols>';
            for ($i = 0; $i < count($columnWidths); $i++) {
                $colsXml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$columnWidths[$i].'" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        // Worksheet
        $sheet = $xmlHeader.'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$colsXml.'<sheetData>';
        
        for ($r = 0; $r < count($data); $r++) {
            $rowXml = '<row r="'.($r+1).'">';
            $row = $data[$r];
            $isHeader = ($r === 0);
            
            for ($c = 0; $c < count($row); $c++) {
                $cellRef = $colLetter($c).($r+1);
                $val = $row[$c];
                
                if ($isHeader) {
                    // Headers as strings
                    $safe = htmlspecialchars((string)$val, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                    $rowXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$safe.'</t></is></c>';
                } elseif (is_int($val) || is_float($val)) {
                    // Numeric values (solo int/float reales, no strings): convertir a entero (dinero sin decimales)
                    $numericValue = (int)round((float)$val);
                    $rowXml .= '<c r="'.$cellRef.'"><v>'.$numericValue.'</v></c>';
                } else {
                    // String values
                    $safe = htmlspecialchars((string)$val, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                    $rowXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$safe.'</t></is></c>';
                }
            }
            $rowXml .= '</row>';
            $sheet .= $rowXml;
        }
        
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $closeResult = $zip->close();
        
        if (!$closeResult) {
            @unlink($tmpXlsx);
            throw new Exception('Error al finalizar la creación del archivo Excel');
        }

        // Verificar que el archivo se creó correctamente
        if (!file_exists($tmpXlsx) || filesize($tmpXlsx) === 0) {
            @unlink($tmpXlsx);
            throw new Exception('El archivo Excel no se generó correctamente');
        }

        // Limpiar cualquier salida previa
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Send file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . filesize($tmpXlsx));
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        readfile($tmpXlsx);
        @unlink($tmpXlsx);
        exit;
    }
}
?>

