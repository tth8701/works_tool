<?php
    //include class
    require("Numbers/Words.php");
	require("fpdf17/fpdf.php");
		
    //phpinfo();
	
	$date_fmt = "M jS, Y";
	$chq_print_dt = "Date:      ".date($date_fmt);    // Set the cheque printing date
	
	$bounce_pecent = "1%";                            // Bounce percent as 1%

	//Create begging and end quarter date
	$n = (date('n') - 1);
	$curr_year = date('Y');
    if($n < 4){
    	$begQtrDt = $curr_year.'-01-01';
		$endQtrDt = $curr_year.'-03-31';
    } elseif($n > 3 && $n <7){
    	$begQtrDt = $curr_year.'-04-01';
		$endQtrDt = $curr_year.'-06-30';
	} elseif($n >6 && $n < 10){
		$begQtrDt = $curr_year.'-07-01';
		$endQtrDt = $curr_year.'-09-30';
	} elseif($n >9){
		$begQtrDt = $curr_year.'-10-01';
		$endQtrDt = $curr_year.'-12-31';
    }
	
	//  Create current quarter beg and end date in the cheque format
	$begQtr = date($date_fmt,strtotime($begQtrDt));
	$endQtr = date($date_fmt,strtotime($endQtrDt));
	
	$days = daysDiff($begQtrDt, $endQtrDt);
	
	//  Create the date difference function for the current quarter
	function daysDiff ($begDt, $endDt) {
		$dStart = new DateTime($begDt);
		$dEnd  = new DateTime($endDt);
		$dDiff = $dStart->diff($dEnd);
		$numbsDays = $dDiff->format('%a');
		return ($numbsDays+1);	            //   Count the first day
	}
	
	//   Create a functon for comments
	function cmtCreate ($arrValue) {
			global $intQtr, $bounce_pecent, $days, $endQtrDt;
			$_commenceDt = $arrValue[2];                                       //   Dividend commenct date             
			$_units = number_format($arrValue[6],0,'',',');                    //   Total units per unit holder
			$_divAmt = number_format($arrValue[7],2,'.',',');                  //   Dividend amount
			$_bonus = $arrValue[5];                                            //   Bonus indicator
			$_bonusAmt = number_format($arrValue[8],2,'.',',');                //   Bonus amount 
	
			//   Create divident period and amount detail function
			if (empty($_commenceDt)) {             //   Full quarter dividends
				$_divComment = "$".$intQtr."/u*".$_units."u=$".$_divAmt;
				} else {                          //   Partial period dividends
					$_divPeriod = daysDiff($_commenceDt, $endQtrDt);
					$_divComment = "$".$intQtr."/u*".$_units."u divided by " .$days." days *".$_divPeriod." days=$".$_divAmt;
				}
			
			//   Create bonus detail
			if (!empty($_bonus)) {
				$_bonusComment = $bounce_pecent."$".$_bonusAmt;
				$rsltArr = array($_divComment, $_bonusComment);
			} else {$rsltArr = array($_divComment);}
			
			return $rsltArr;
	}

	$formattedArr = array();
	$filename = "DX-Investment Changes.csv";
	//  CSV to multidimensional array in php
	if (($handle = fopen($filename, "r")) !== FALSE) {
    	$key = 0;    // Set the array key.
    	while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
       		$count = count($data);  //  Get the total keys in row
        	//  Insert data to our array
        	for ($i=0; $i < $count; $i++) {
            	$formattedArr[$key][$i] = $data[$i];     //  Without count the head row
        	}
        	$key++;
    	}
    	$headArr = array_shift($formattedArr);
    	fclose($handle);    //  Close file handle
	}
		
	//  Check if the second row has now divident receiver's name, if not, term the progam and issue an error
	if (empty($formattedArr[0][0])) {
			exit("The first row of data file has blanked receiver's name. Please review the file");
		}
	
	$intQtr = round($formattedArr[0][4] * $days,5);           //  Quarterly Interest
		
	$count = 0;
	foreach ($formattedArr as $value) {
		$commentArr = cmtCreate($value);
		$recipient = $value[0];                //   The dividens receiver's name
		
		if (!empty($recipient)) {
			$commenceDt = $value[2];               //   Dividend commenct date 
			//   If a unit holder's investment didn't start at the quarter beginning
			if (!empty($commenceDt)) {             
				$begQtr = date($date_fmt,strtotime($commenceDt));
				} else {
					$begQtr = date($date_fmt,strtotime($begQtrDt));
				}
			$distbPeriod = $begQtr." to ".$endQtr;  //  Current distribution period
			
			$grandTot = $value[9];
			if (!empty($grandTot)) {
				$grandTot = round($grandTot,2);
				
				$grandTotWords = Numbers_Words::toCurrency($grandTot);
				$grandTotWords = strtoupper ($grandTotWords);
			
				//   Create a final array for each unit holder only one row of entry
				$intemdArr = array($grandTotWords, $grandTot, $recipient, $distbPeriod);
				//   Finalize the array for the unit holder
				${"chqArr".$count}= array_merge($intemdArr, $commentArr);
				$count++;
			} else {	
				//   Create and keep an array for each unit holder with more than one row of entry
				$intemdArr = array($recipient, $distbPeriod);
				${"chqArr".$count}= array_merge($intemdArr, $commentArr);
		 	} 
		} else {
			$grandTot = $value[9];
			if (!empty($grandTot)) {
				$grandTot = round($grandTot,2);
				
				$grandTotWords = Numbers_Words::toCurrency($grandTot);
				$grandTotWords = strtoupper ($grandTotWords);
			
				//   Create a final array for each unit holder with more than one row of entry
				$intemdArr2 = array($grandTotWords, $grandTot);
				${"chqArr".$count}= array_merge($intemdArr2, ${"chqArr".$count}, $commentArr);
				//   Finalize the array for the unit holder
				$count++;
			} else {	
				//   Create and keep an array for each unit holder with more than one row of entry
				${"chqArr".$count}= array_merge(${"chqArr".$count}, $commentArr);
		 	} 
		}
	}
					
			 /* 
			     If an unit holder has more than one row for one's dividend payout 
			     it will continue the reading to next line until the grand total is found
				 Otherwise report directly
			 */ 


	//   Create the dividend interest total adding and total amount comment
	for ($i=0; $i<$count; $i++) {
		if (!empty(${"chqArr".$i})) {
			${"chqArr".$i}[1] = number_format(${"chqArr".$i}[1],2,'.',',');
			$total = ("$".${"chqArr".$i}[1]);
			$name = ${"chqArr".$i}[2];
			$totBreak = (count(${"chqArr".$i}) - 4);
			if ($totBreak == 1) {
				$totComments = array(("Total ".$total." paid to ".$name." on ".date($date_fmt)."."), ($totBreak+2));
			} else {
				$totCalR = "=".$total;
				for ($j=1; $j<=$totBreak; $j++) {
					${"chqArr".$i}[$j-1+4] = $j.". ".${"chqArr".$i}[$j-1+4];
					if ($j==1) {
						$totCalL = "1";
					} else {
						$totCalL = $totCalL."+".$j;
					}
				}
				$totCal = $totCalL.$totCalR;
				$totComments = array($totCal, ("Total ".$total." paid to ".$name." on ".date($date_fmt)."."), ($totBreak+3));
			}
			${"chqArr".$i} = array_merge(${"chqArr".$i}, $totComments);
		} else {                                                          //   If there is undefined row in file, error out
			exit("The file has a row with empty or zero data. Please review");
		}
	}
	
	//print_r($chqArr3);
	//exit;
	
	//   Create PDF file
	$pdf = new FPDF();
	$pdf->SetTopMargin(25);
	$pdf->SetLeftMargin(18);
	for ($m=0; $m<$count; $m++) {
		$chqArr = ${"chqArr".$m};
		$pdf->AddPage(); 
		$pdf->SetFont('Times','B',12);
		$pdf->Cell(137,4,'');
		$pdf->Cell(38,4,$chq_print_dt,0,0,'R');
		$pdf->Ln(10);
		$pdf->SetFont('Times','',9);
		$pdf->Cell(147,4,$chqArr[0]);
		$pdf->SetFont('Times','B',12);
		$pdf->Cell(29,4,$chqArr[1],0,0,'R');
		$pdf->Ln(19);
		$pdf->Cell(60,4,$chqArr[2]);
		for ($k=0; $k<2; $k++) {
			if ($k==0) {
				$pdf->Ln(55);
			} else {
				$pdf->Ln(85);
			}
			for ($l=1; $l<=end($chqArr); $l++) {
				$pdf->Cell(130,4,$chqArr[2+$l],0,1);
			}
		}
	}
	$pdf->Output('C:\Users\Tim\Documents\Aptana Studio 3 Workspace\works_tool\cheques.pdf','F');
?> 