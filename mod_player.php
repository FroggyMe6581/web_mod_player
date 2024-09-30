<?php

if(count($_GET) == 0)
   display_files();
else
   gen_data_struct();

function display_files()
{
   $mods = array();
   $dir = "./mods/";
   if( is_dir($dir) )
   {
      if( $dh = opendir($dir) )
      {
         while (($file = readdir($dh)) !== false)
            if( ($file != ".") && ($file != "..") )
               $mods[] = $file;
         
         closedir($dh);
      }
   }

   sort($mods);

   echo "<!doctype html>
<html>
   <head>
      <title>FroggyMe's MOD Player in JavaScript and PHP</title>
      <script src=\"jquery-1.10.2.min.js\"></script>
   </head>
   <body>
      <br>
      <a href=\"http://pwh.sdf.org/web_mod_player\">HOME</a>
      <br>
      <h2>Select a song below:</h2>
      <br>
      <br>\n\n";

   foreach($mods as $mod)
   {
      echo "<div style=\"padding: 5px; padding-bottom: 1px; border: solid black 1px;\">\n";
      echo "<a href=\"mod_player.php?filename={$mod}\">{$mod}</a></div>\n";
   }

   echo "</body></html>\n";
}

function gen_data_struct()
{
   $filename = $_GET["filename"];

   $mod_found = 0;
   
   $mods = array();
   $dir = "./mods/";
   if( is_dir($dir) )
   {
      if( $dh = opendir($dir) )
      {
         while (($file = readdir($dh)) !== false)
            if( ($file != ".") && ($file != "..") )
               $mods[] = $file;
         
         closedir($dh);
      }
   }

   sort($mods);

   echo "<!doctype html>
<html>
   <head>
      <title>FroggyMe's MOD Player in JavaScript and PHP</title>
      <script src=\"jquery-1.10.2.min.js\"></script>
   </head>
   <body>
      <br>
      <a href=\"http://pwh.sdf.org/web_mod_player\">HOME</a>
      <br>
      <br>
      <a href=\"mod_player.php\">[Back to song list]</a>
      <br>
      <br>\n\n";

   foreach($mods as $mod)
   {
      if($mod == $filename)
      {
         $mod_found = 1;
      }
   }

   if($mod_found == 0)
   {
      echo "\nFile not found.\n";
      echo "</body></html>\n";
      return;
   }

   echo "<br>\n";
   echo "<button style=\"height:200px;width:200px;font-size:xx-large\" onclick=\"instance()\">PLAY</button>\n";
   echo "<br><br>\n";
   echo "<div style=\"font-size:50px\"><a href=\"mod_player.php\">STOP</a></div>\n";
   echo "<br><br>\n\n";
   
   $filename = "mods/{$filename}";
   $mod_file = fopen($filename, "rb");

   $sample_names = array();
   $sample_lengths = array();
   $sample_finetunes = array();
   $sample_vols = array();
   $sample_repeat_offsets = array();       // position in bytes.
   $sample_repeat_lengths = array();       // length in bytes.

   $pattern_order = array();

   $song_title = rtrim(fread($mod_file, 20));
   for($snum = 0; $snum < 31; $snum++)
   {
      $sample_names[] = rtrim(fread($mod_file, 22));
      ////////////////////////////////////////////////////////////////
      $sample_length = bin2hex(fread($mod_file, 2));
      //echo "sample_length bin2hex = {$sample_length}\n";
      //echo "sample_length[0]  hex = {$sample_length[0]}\n";
      //echo "sample_length[1]  hex = {$sample_length[1]}\n";
      //echo "sample_length[2]  hex = {$sample_length[2]}\n";
      //echo "sample_length[3]  hex = {$sample_length[3]}\n\n";
      $sample_length = hex2dec($sample_length[3]) + 16*hex2dec($sample_length[2]) + (16*16)*hex2dec($sample_length[1]) + (16*16*16)*hex2dec($sample_length[0]);  //Amiga's are
                                                                                                                                                                 //Big-endian.
                                                                                                                                                                 //(Motorolla 680x0)
      $sample_length *= 2; // length of sample in bytes.

      $sample_lengths[] = $sample_length;
      ////////////////////////////////////////////////////////////////
      $sample_finetune = bin2hex(fread($mod_file, 1));
      $sample_finetune = hex2dec($sample_finetune[1]) + 16*hex2dec($sample_finetune[0]);

      $sample_finetunes[] = $sample_finetune;
      //////////////////////////////////////////////////////////////////
      $sample_volume = bin2hex(fread($mod_file, 1));
      $sample_volume = hex2dec($sample_volume[1]) + 16*hex2dec($sample_volume[0]);

      $sample_vols[] = $sample_volume;
      //////////////////////////////////////////////////////////////////
      $sample_ro = bin2hex(fread($mod_file, 2));
      $sample_ro = hex2dec($sample_ro[3]) + 16*hex2dec($sample_ro[2]) + (16*16)*hex2dec($sample_ro[1]) + (16*16*16)*hex2dec($sample_ro[0]);
      $sample_ro *= 2; // position of repeat offset in bytes.

      $sample_repeat_offsets[] = $sample_ro;
      //////////////////////////////////////////////////////////////////
      $sample_rl = bin2hex(fread($mod_file, 2));
      $sample_rl = hex2dec($sample_rl[3]) + 16*hex2dec($sample_rl[2]) + (16*16)*hex2dec($sample_rl[1]) + (16*16*16)*hex2dec($sample_rl[0]);
      $sample_rl *= 2; // position of repeat length in bytes.

      $sample_repeat_lengths[] = $sample_rl;
   }

   //Now get pattern list data
   $num_patterns = bin2hex(fread($mod_file, 1));                                 //$num_patterns is number played, not number in file
   $num_patterns = hex2dec($num_patterns[1]) + 16*hex2dec($num_patterns[0]);
   
   $song_end_jump_pos = bin2hex(fread($mod_file, 1));
   $song_end_jump_pos = hex2dec($song_end_jump_pos[1]) + 16*hex2dec($song_end_jump_pos[0]);

   
   $num_patterns_in_file = 0;
   for($p = 0; $p < 128; $p++)
   {
      $pattern = bin2hex(fread($mod_file, 1));
      $pattern = hex2dec($pattern[1]) + 16*hex2dec($pattern[0]);

      $pattern_order[] = $pattern;
      if($pattern > $num_patterns_in_file)
         $num_patterns_in_file = $pattern;
   }
   $num_patterns_in_file++;

   # $played_patterns = array_slice($pattern_order, 0, $num_patterns);
   # $unique_pattern_num = count(array_unique($played_patterns));


################################################
   $number_of_samples = 0;  //with data, not all 31.
   for($s = 1; $s <= 31; $s++)
      if($sample_lengths[$s-1] != 0)
         $number_of_samples++;
   #echo "Number of samples: <div id=number_of_samples>{$number_of_samples}</div>\n";
###############################################


   // MOD file type
   $mod_type = fread($mod_file, 4);    //all 4 bytes valid ASCII chars.

   echo "<script type=\"text/javascript\">

   	   // some global variables
         var sampleRate = 44100;
         
         var page_loaded = false;
         var playing = false;
			var audioDestination;
         
         var ch1samp = 0, ch2samp = 0, ch3samp = 0, ch4samp = 0;
         var ch1period = 0, ch2period = 0, ch3period = 0, ch4period = 0;
         var ch1effect = 0, ch2effect = 0, ch3effect = 0, ch4effect = 0;
         var ch1effect_value = 0, ch2effect_value = 0, ch3effect_value = 0, ch4effect_value = 0;
         var ch1_vol = 64, ch2_vol = 64, ch3_vol = 64, ch4_vol = 64;
         var ch1sampLength = 0, ch2sampLength = 0, ch3sampLength = 0, ch4sampLength = 0;
         var ch1sampLength_rs = 0, ch2sampLength_rs = 0, ch3sampLength_rs = 0, ch4sampLength_rs = 0;
         var ch1SampPos = 0, ch2SampPos = 0, ch3SampPos = 0, ch4SampPos = 0;

			var ch1_loops = 0, ch2_loops = 0, ch3_loops = 0, ch4_loops = 0;
			var ch1_looping = 0, ch2_looping = 0, ch3_looping = 0, ch4_looping = 0;
			var ch1_ro_rs = 0, ch2_ro_rs = 0, ch3_ro_rs = 0, ch4_ro_rs = 0;
			var ch1_rl_rs = 0, ch2_rl_rs = 0, ch3_rl_rs = 0, ch4_rl_rs = 0;
			var ch1_re_rs = 0, ch2_re_rs = 0, ch3_re_rs = 0, ch4_re_rs = 0;
         
         var current_row = 0;
         var samples_done = 0;   //for counting up to 5292 per row.
         var current_pattern_num = 0;
         var current_pattern = 0;
         var num_pat_played = 0;
         var pattern_reloop_index = 0;
         
         var ch1 = 0, ch2 = 0, ch3 = 0, ch4 = 0;
         var ch1_on = 0, ch2_on = 0, ch3_on = 0, ch4_on = 0;

	      var ticksPerRow = 6; //equals song speed and used for samples per row.
	      var TIME_PER_TICK_SECONDS = 0.02;
	      var samples_per_row;
	      var samples_per_row_next;

			var leadinsegs = 0;
	      
	      // Effect constants
	      var SET_VOLUME = 12;    //Cxx - Set Volume 0 to 64.
	      var PATTERN_BREAK = 13; //Dxy - Pattern Break: goto row 10*x + y (effect is legible base 10).
	      var SET_SPEED = 15;     //Fxx - Set Speed to number of ticks per row.
	      
	      //END GLOBALS

			// multiply frequency by amount to fine-tune (multiple k of 1/8th of a semitone = 2^(k/12/8)), so multiply period by 2^(-k/12/8).
			// input k is in 2s complement.
			function period_mult(fine_tune)
			{
				var pm = 1;		// period multiplier

				switch(fine_tune)
				{
					case 0:
						pm = 1;				// 2^(-0/12/8)
						break;
					case 1:
						pm = 0.99280572;	// 2^(-1/12/8)
						break;
					case 2:
						pm = 0.985663199;	// 2^(-2/12/8)
						break;
					case 3:
						pm = 0.978572062;	// 2^(-3/12/8)
						break;
					case 4:
						pm = 0.971531941;	// 2^(-4/12/8)
						break;
					case 5:
						pm = 0.964542469;	// 2^(-5/12/8)
						break;
					case 6:
						pm = 0.957603281;	// 2^(-6/12/8)
						break;
					case 7:
						pm = 0.950714015;	// 2^(-7/12/8)
						break;
					case 8:
						pm = 1.059463094;	// 2^(-(-8)/12/8)
						break;
					case 9:
						pm = 1.051841021;	// 2^(-(-7)/12/8)
						break;
					case 10:
						pm = 1.044273782;	// 2^(-(-6)/12/8)
						break;
					case 11:
						pm = 1.036760985;	// 2^(-(-5)/12/8)
						break;
					case 12:
						pm = 1.029302237;	// 2^(-(-4)/12/8)
						break;
					case 13:
						pm = 1.021897149;	// 2^(-(-3)/12/8)
						break;
					case 14:
						pm = 1.014545335;	// 2^(-(-2)/12/8)
						break;
					case 15:
						pm = 1.007246412;	// 2^(-(-1)/12/8)
						break;
					default:
						pm = 1;	// safely mult by 1.
				}

				return pm;
			}
   
   	   //period already adjusted by multiples of 1/8th of a semitone using sample_finetunes[], to adjust frequency returned.
   	   function resample_playback_rate(period_in_cycles)
	      {
  	         var amiga_3_5MHz_clock_freq_NTSC = 7159090.5/2; /* NTSC frequency */
	      	return amiga_3_5MHz_clock_freq_NTSC/period_in_cycles; // this will multiply frequency by 2^(twos_comp(finetune)/12/8), as period is already multiplied by 1/that.
	      }

         function resample(samples_resampled, samples, sampleNumber, interpolate, decimate, sampLength)
         {
         	var rs_index = 0;
         	var int_index = 0;
        		for (var pos = 0; pos < sampLength; pos++)
        		{
	         	for (var i = 0; i < (interpolate/decimate); i++)
					{
						if(int_index < interpolate)
						{
							samples_resampled[rs_index++] = samples[sampleNumber-1][pos];
							int_index = int_index + decimate;
						}
					}
					int_index = int_index % interpolate;
        		}
         } // rs_index is the length, can be an argument to get length out of function
         
      $(document).ready(function () {
         samples = [];
         sample_vols = [];
			sample_lengths = [];
			sample_loop_offset = [];
			sample_loop_length = [];
         sample_finetunes = [];
   for(var s = 0; s < 31 /* parseInt($(\"#number_of_samples\").text()) */ ; s++)
   {
      if( parseInt($(\"#s\"+(s+1)+\"_length\").text()) > 0 )
      {
         samples[s] = $(\"#sample_data_\" + (s+1)).text().split(\".\");
         sample_vols[s] = parseInt($(\"#s\"+(s+1)+\"_vol\").text());
			sample_lengths[s] = parseInt($(\"#s\"+(s+1)+\"_length\").text());
			sample_loop_offset[s] = parseInt($(\"#s\"+(s+1)+\"_ro\").text());
			sample_loop_length[s] = parseInt($(\"#s\"+(s+1)+\"_rl\").text());
			sample_finetunes[s] = parseInt($(\"#s\"+(s+1)+\"_finetune\").text());
      }
   }
   
   // fill in function at read times
   samples_resampled_ch1 = [];
   samples_resampled_ch2 = [];
   samples_resampled_ch3 = [];
   samples_resampled_ch4 = [];
   			
   			
	patterns = [];
   for(var pat = 0; pat < parseInt($(\"#num_patterns_in_file\").text()); pat++)
   {
      patterns[pat] = [];
      for(var row = 0; row < 64; row++)
      {
         patterns[pat][row] = [];
         for(var chan = 1; chan <= 4; chan++)
         {
	         patterns[pat][row][chan] = {sample: parseInt($(\"#p\"+pat+\"r\"+row+\"ch\"+chan+\"sample\").text()), period: parseInt($(\"#p\"+pat+\"r\"+row+\"ch\"+chan+\"period\").text()), effect: parseInt($(\"#p\"+pat+\"r\"+row+\"ch\"+chan+\"effect\").text()), effect_value: parseInt($(\"#p\"+pat+\"r\"+row+\"ch\"+chan+\"effectvalue\").text())};
         }
      }
   }

   num_pat_played = parseInt($(\"#num_patterns_played\").text());
   pattern_table = [];
   for(var p = 0; p < num_pat_played; p++)
   {
      pattern_table[p] = parseInt($(\"#p\"+p+\"_num\").text());     // id=p1_num
   }
   
   current_pattern_num = 0;
   current_pattern = pattern_table[current_pattern_num];
   pattern_reloop_index = parseInt($(\"#song_end_jump_pos\").text());

   ch1samp = patterns[current_pattern][current_row][1].sample;
   ch2samp = patterns[current_pattern][current_row][2].sample;
   ch3samp = patterns[current_pattern][current_row][3].sample;
   ch4samp = patterns[current_pattern][current_row][4].sample;

   ch1period = patterns[current_pattern][current_row][1].period;
   ch2period = patterns[current_pattern][current_row][2].period;
   ch3period = patterns[current_pattern][current_row][3].period;
   ch4period = patterns[current_pattern][current_row][4].period;
   
   ch1effect = patterns[current_pattern][current_row][1].effect;
   ch2effect = patterns[current_pattern][current_row][2].effect;
   ch3effect = patterns[current_pattern][current_row][3].effect;
   ch4effect = patterns[current_pattern][current_row][4].effect;
   
   ch1effect_value = patterns[current_pattern][current_row][1].effect_value;
   ch2effect_value = patterns[current_pattern][current_row][2].effect_value;
   ch3effect_value = patterns[current_pattern][current_row][3].effect_value;
   ch4effect_value = patterns[current_pattern][current_row][4].effect_value;

   if(ch1samp != 0)
      ch1sampLength = sample_lengths[ch1samp-1];
   if(ch2samp != 0)
      ch2sampLength = sample_lengths[ch2samp-1];
   if(ch3samp != 0)
      ch3sampLength = sample_lengths[ch3samp-1];
   if(ch4samp != 0)
      ch4sampLength = sample_lengths[ch4samp-1];
   
   if(ch1period != 0)
   {
   	ch1_on = 1;
   	var resample_freq = resample_playback_rate(ch1period * period_mult(sample_finetunes[ch1samp-1]));
   	resample(samples_resampled_ch1, samples, ch1samp, sampleRate, resample_freq, ch1sampLength);
	   ch1sampLength_rs = Math.ceil(ch1sampLength*sampleRate/resample_freq);
		if(sample_loop_length[ch1samp-1] > 2)
		{
			ch1_loops = 1;
			ch1_looping = 0;
			ch1_ro_rs = Math.ceil(sample_loop_offset[ch1samp-1]*sampleRate/resample_freq);
			ch1_rl_rs = Math.ceil(sample_loop_length[ch1samp-1]*sampleRate/resample_freq);
			ch1_re_rs = ch1_ro_rs + ch1_rl_rs;
			if(ch1_re_rs > ch1sampLength_rs)
			{
				ch1_re_rs = ch1sampLength_rs;
			}
		}
		else
		{
			ch1_loops = 0;
			ch1_looping = 0;
		}
   }
   
   if(ch2period != 0)
   {
	   ch2_on = 1;
   	var resample_freq = resample_playback_rate(ch2period * period_mult(sample_finetunes[ch2samp-1]));
   	resample(samples_resampled_ch2, samples, ch2samp, sampleRate, resample_freq, ch2sampLength);
	   ch2sampLength_rs = Math.ceil(ch2sampLength*sampleRate/resample_freq);
		if(sample_loop_length[ch2samp-1] > 2)
		{
			ch2_loops = 1;
			ch2_looping = 0;
			ch2_ro_rs = Math.ceil(sample_loop_offset[ch2samp-1]*sampleRate/resample_freq);
			ch2_rl_rs = Math.ceil(sample_loop_length[ch2samp-1]*sampleRate/resample_freq);
			ch2_re_rs = ch2_ro_rs + ch2_rl_rs;
			if(ch2_re_rs > ch2sampLength_rs)
			{
				ch2_re_rs = ch2sampLength_rs;
			}
		}
		else
		{
			ch2_loops = 0;
			ch2_looping = 0;
		}
   }
   
   if(ch3period != 0)
   {
   	ch3_on = 1;
   	var resample_freq = resample_playback_rate(ch3period * period_mult(sample_finetunes[ch3samp-1]));
   	resample(samples_resampled_ch3, samples, ch3samp, sampleRate, resample_freq, ch3sampLength);
	   ch3sampLength_rs = Math.ceil(ch3sampLength*sampleRate/resample_freq);
		if(sample_loop_length[ch3samp-1] > 2)
		{
			ch3_loops = 1;
			ch3_looping = 0;
			ch3_ro_rs = Math.ceil(sample_loop_offset[ch3samp-1]*sampleRate/resample_freq);
			ch3_rl_rs = Math.ceil(sample_loop_length[ch3samp-1]*sampleRate/resample_freq);
			ch3_re_rs = ch3_ro_rs + ch3_rl_rs;
			if(ch3_re_rs > ch3sampLength_rs)
			{
				ch3_re_rs = ch3sampLength_rs;
			}
		}
		else
		{
			ch3_loops = 0;
			ch3_looping = 0;
		}
   }
   
   if(ch4period != 0)
   {
   	ch4_on = 1;
   	var resample_freq = resample_playback_rate(ch4period * period_mult(sample_finetunes[ch4samp-1]));
   	resample(samples_resampled_ch4, samples, ch4samp, sampleRate, resample_freq, ch4sampLength);
	   ch4sampLength_rs = Math.ceil(ch4sampLength*sampleRate/resample_freq);
		if(sample_loop_length[ch4samp-1] > 2)
		{
			ch4_loops = 1;
			ch4_looping = 0;
			ch4_ro_rs = Math.ceil(sample_loop_offset[ch4samp-1]*sampleRate/resample_freq);
			ch4_rl_rs = Math.ceil(sample_loop_length[ch4samp-1]*sampleRate/resample_freq);
			ch4_re_rs = ch4_ro_rs + ch4_rl_rs;
			if(ch4_re_rs > ch4sampLength_rs)
			{
				ch4_re_rs = ch4sampLength_rs;
			}
		}
		else
		{
			ch4_loops = 0;
			ch4_looping = 0;
		}
   }
   
   if(ch1effect == SET_SPEED)
   {
   	ticksPerRow = ch1effect_value;
   }
   if(ch2effect == SET_SPEED)
   {
   	ticksPerRow = ch2effect_value;
   }
   if(ch3effect == SET_SPEED)
   {
   	ticksPerRow = ch3effect_value;
   }
   if(ch4effect == SET_SPEED)
   {
   	ticksPerRow = ch4effect_value;
   }
   samples_per_row = ticksPerRow*TIME_PER_TICK_SECONDS*sampleRate;	// 5292 for song speed 6.
   samples_per_row_next = samples_per_row;
   
   if(ch1effect == SET_VOLUME)
   {
   	ch1_vol = ch1effect_value;
   	if(ch1_vol > 64)
   		ch1_vol = 64;
   }
   if(ch2effect == SET_VOLUME)
   {
   	ch2_vol = ch2effect_value;
   	if(ch2_vol > 64)
   		ch2_vol = 64;
   }
   if(ch3effect == SET_VOLUME)
   {
   	ch3_vol = ch3effect_value;
   	if(ch3_vol > 64)
   		ch3_vol = 64;
   }
   if(ch4effect == SET_VOLUME)
   {
   	ch4_vol = ch4effect_value;
   	if(ch4_vol > 64)
   		ch4_vol = 64;
   }

   page_loaded = true;
      
         });
         
			function AudioDataDestination(sampleRate, readFn)
         {
				// Initialize the audio output.
            var AudioContext = window.AudioContext || window.webkitAudioContext;
				var audioCtx = new AudioContext();

				var audiobuffer = [];
				audiobuffer[0] = audioCtx.createBuffer(2, sampleRate*8, sampleRate); // create two 8 second buffers.
				audiobuffer[1] = audioCtx.createBuffer(2, sampleRate*8, sampleRate);

				var timeToPlay = 0;
				var currentBuffer = 0;

				// SAME AS FUNCTION IN setInterval:
				var source = audioCtx.createBufferSource();
				var soundDataL = audiobuffer[currentBuffer].getChannelData(0);
				var soundDataR = audiobuffer[currentBuffer].getChannelData(1);
				readFn(soundDataL, soundDataR, audiobuffer[currentBuffer].length);               // calls function requestSoundData(soundData)
				source.buffer = audiobuffer[currentBuffer];
				source.connect(audioCtx.destination);
				source.start(timeToPlay);
				timeToPlay = audioCtx.currentTime + audiobuffer[currentBuffer].duration; // offset by how long it took to get going.
				currentBuffer++;
				// END SAME FUNCTION IN setInterval

				var numCalls = 0;

            // The function called with regular interval to populate 
            // the audio output buffer.
            setInterval(function() {
					if(numCalls%16 == 2 || numCalls%16 == 10) // (was 1 and 9, one second into the current buffer, populate the other buffer.) two seconds in, populate
					{
						var source = audioCtx.createBufferSource();

						var soundDataL = audiobuffer[currentBuffer%2].getChannelData(0);
						var soundDataR = audiobuffer[currentBuffer%2].getChannelData(1);

						readFn(soundDataL, soundDataR, audiobuffer[currentBuffer%2].length);               // calls function requestSoundData(soundData)

						source.buffer = audiobuffer[currentBuffer%2];
						source.connect(audioCtx.destination);
						source.start(timeToPlay);

						timeToPlay = timeToPlay + audiobuffer[currentBuffer%2].duration;

						currentBuffer++;
					}
					numCalls += 2; // numCalls is num seconds

            }, 2000); //every 2 seconds
         }  //END function AudioDataDestination(sampleRate, readFn)

         // Control and generate the sound.

         // get more data from arrays representing mod and create soundData.length/2 samples per stereo channel
         function requestSoundData(soundDataL, soundDataR, size)
         {
            for (var i = 0; i < size; i++)
            {
               if(samples_done >= samples_per_row)	//get samples-per-row from song-speed and sample-rate and time-per-tick.
               {
               	samples_per_row = samples_per_row_next;
                  samples_done = 0;
                  
                  current_row++;
                  if(current_row >= 64)
                  {
                     current_row = 0;
                     current_pattern_num++;
                     // logic for when patterns played > patterns played in song (current_pattern_num = start index).
                     if(current_pattern_num >= num_pat_played)
                     {
                     	current_pattern_num = pattern_reloop_index;
                     }
                     current_pattern = pattern_table[current_pattern_num];
                  }
                  
                  // new things
                  ch1samptemp = patterns[current_pattern][current_row][1].sample;
                  ch2samptemp = patterns[current_pattern][current_row][2].sample;
                  ch3samptemp = patterns[current_pattern][current_row][3].sample;
                  ch4samptemp = patterns[current_pattern][current_row][4].sample;

                  if(ch1samptemp != 0)
                  {
                     ch1samp = ch1samptemp;
                     ch1sampLength = sample_lengths[ch1samp-1];
                     ch1SampPos = 0;
							ch1_vol = 64; // reset for new sample, may adjust by effect below.  not resetting is silencing channels.
                  }
                  if(ch2samptemp != 0)
                  {
                     ch2samp = ch2samptemp;
                     ch2sampLength = sample_lengths[ch2samp-1];
                     ch2SampPos = 0;
							ch2_vol = 64; // reset for new sample, may adjust by effect below.  not resetting is silencing channels.
                  }
                  if(ch3samptemp != 0)
                  {
                     ch3samp = ch3samptemp;
                     ch3sampLength = sample_lengths[ch3samp-1];
                     ch3SampPos = 0;
							ch3_vol = 64; // reset for new sample, may adjust by effect below.  not resetting is silencing channels.
                  }
                  if(ch4samptemp != 0)
                  {
                     ch4samp = ch4samptemp;
                     ch4sampLength = sample_lengths[ch4samp-1];
                     ch4SampPos = 0;
							ch4_vol = 64; // reset for new sample, may adjust by effect below.  not resetting is silencing channels.
                  }

                  ch1periodtemp = patterns[current_pattern][current_row][1].period;
                  ch2periodtemp = patterns[current_pattern][current_row][2].period;
                  ch3periodtemp = patterns[current_pattern][current_row][3].period;
                  ch4periodtemp = patterns[current_pattern][current_row][4].period;

                  if(ch1periodtemp != 0)
                  {
                  	ch1_on = 1;
                     ch1period = ch1periodtemp;
                     ch1SampPos = 0;
                     
                     samples_resampled_ch1 = [];
                     
                     var resample_freq = resample_playback_rate(ch1period * period_mult(sample_finetunes[ch1samp-1]));
                     resample(samples_resampled_ch1, samples, ch1samp, sampleRate, resample_freq, ch1sampLength);
                     ch1sampLength_rs = Math.ceil(ch1sampLength*sampleRate/resample_freq);

							if(sample_loop_length[ch1samp-1] > 2)
							{
								ch1_loops = 1;
								ch1_looping = 0;
								ch1_ro_rs = Math.ceil(sample_loop_offset[ch1samp-1]*sampleRate/resample_freq);
								ch1_rl_rs = Math.ceil(sample_loop_length[ch1samp-1]*sampleRate/resample_freq);
								ch1_re_rs = ch1_ro_rs + ch1_rl_rs;
								if(ch1_re_rs > ch1sampLength_rs)
								{
									ch1_re_rs = ch1sampLength_rs;
								}
							}
							else
							{
								ch1_loops = 0;
								ch1_looping = 0;
							}
                  }
                  if(ch2periodtemp != 0)
                  {
                  	ch2_on = 1;
                     ch2period = ch2periodtemp;
                     ch2SampPos = 0;
                     
                     samples_resampled_ch2 = [];
                     
                     var resample_freq = resample_playback_rate(ch2period * period_mult(sample_finetunes[ch2samp-1]));
                     resample(samples_resampled_ch2, samples, ch2samp, sampleRate, resample_freq, ch2sampLength);
                     ch2sampLength_rs = Math.ceil(ch2sampLength*sampleRate/resample_freq);

							if(sample_loop_length[ch2samp-1] > 2)
							{
								ch2_loops = 1;
								ch2_looping = 0;
								ch2_ro_rs = Math.ceil(sample_loop_offset[ch2samp-1]*sampleRate/resample_freq);
								ch2_rl_rs = Math.ceil(sample_loop_length[ch2samp-1]*sampleRate/resample_freq);
								ch2_re_rs = ch2_ro_rs + ch2_rl_rs;
								if(ch2_re_rs > ch2sampLength_rs)
								{
									ch2_re_rs = ch2sampLength_rs;
								}
							}
							else
							{
								ch2_loops = 0;
								ch2_looping = 0;
							}
                  }
                  if(ch3periodtemp != 0)
                  {
                  	ch3_on = 1;
                     ch3period = ch3periodtemp;
                     ch3SampPos = 0;
                     
                     samples_resampled_ch3 = [];
                     
                     var resample_freq = resample_playback_rate(ch3period * period_mult(sample_finetunes[ch3samp-1]));
                     resample(samples_resampled_ch3, samples, ch3samp, sampleRate, resample_freq, ch3sampLength);
                     ch3sampLength_rs = Math.ceil(ch3sampLength*sampleRate/resample_freq);

							if(sample_loop_length[ch3samp-1] > 2)
							{
								ch3_loops = 1;
								ch3_looping = 0;
								ch3_ro_rs = Math.ceil(sample_loop_offset[ch3samp-1]*sampleRate/resample_freq);
								ch3_rl_rs = Math.ceil(sample_loop_length[ch3samp-1]*sampleRate/resample_freq);
								ch3_re_rs = ch3_ro_rs + ch3_rl_rs;
								if(ch3_re_rs > ch3sampLength_rs)
								{
									ch3_re_rs = ch3sampLength_rs;
								}
							}
							else
							{
								ch3_loops = 0;
								ch3_looping = 0;
							}
                  }
                  if(ch4periodtemp != 0)
                  {
                  	ch4_on = 1;
                     ch4period = ch4periodtemp;
                     ch4SampPos = 0;
                     
                     samples_resampled_ch4 = [];
                     
                     var resample_freq = resample_playback_rate(ch4period * period_mult(sample_finetunes[ch4samp-1]));
                     resample(samples_resampled_ch4, samples, ch4samp, sampleRate, resample_freq, ch4sampLength);
                     ch4sampLength_rs = Math.ceil(ch4sampLength*sampleRate/resample_freq);

							if(sample_loop_length[ch4samp-1] > 2)
							{
								ch4_loops = 1;
								ch4_looping = 0;
								ch4_ro_rs = Math.ceil(sample_loop_offset[ch4samp-1]*sampleRate/resample_freq);
								ch4_rl_rs = Math.ceil(sample_loop_length[ch4samp-1]*sampleRate/resample_freq);
								ch4_re_rs = ch4_ro_rs + ch4_rl_rs;
								if(ch4_re_rs > ch4sampLength_rs)
								{
									ch4_re_rs = ch4sampLength_rs;
								}
							}
							else
							{
								ch4_loops = 0;
								ch4_looping = 0;
							}
                  }
                  
                  ch1effect = patterns[current_pattern][current_row][1].effect;
                  ch2effect = patterns[current_pattern][current_row][2].effect;
                  ch3effect = patterns[current_pattern][current_row][3].effect;
                  ch4effect = patterns[current_pattern][current_row][4].effect;
                  
                  ch1effect_value = patterns[current_pattern][current_row][1].effect_value;
                  ch2effect_value = patterns[current_pattern][current_row][2].effect_value;
                  ch3effect_value = patterns[current_pattern][current_row][3].effect_value;
                  ch4effect_value = patterns[current_pattern][current_row][4].effect_value;
                  
                  // PATTERN BREAK EFFECT
                  if(ch1effect == PATTERN_BREAK)
                  {
                  	var next_pattern_row = 10*Math.floor(ch1effect_value / 16) + (ch1effect_value % 16);
                  	current_row = next_pattern_row - 1; // because current_row++ when moving to next row.
                     current_pattern_num++;
                     // logic for when patterns played > patterns played in song (current_pattern_num = start index).
                     if(current_pattern_num >= num_pat_played)
                     {
                     	current_pattern_num = pattern_reloop_index;
                     }
                     current_pattern = pattern_table[current_pattern_num];
                  }
                  if(ch2effect == PATTERN_BREAK)
                  {
                  	var next_pattern_row = 10*Math.floor(ch2effect_value / 16) + (ch2effect_value % 16);
                  	current_row = next_pattern_row - 1; // because current_row++ when moving to next row.
                     current_pattern_num++;
                     // logic for when patterns played > patterns played in song (current_pattern_num = start index).
                     if(current_pattern_num >= num_pat_played)
                     {
                     	current_pattern_num = pattern_reloop_index;
                     }
                     current_pattern = pattern_table[current_pattern_num];
                  }
                  if(ch3effect == PATTERN_BREAK)
                  {
                  	var next_pattern_row = 10*Math.floor(ch3effect_value / 16) + (ch3effect_value % 16);
                  	current_row = next_pattern_row - 1; // because current_row++ when moving to next row.
                     current_pattern_num++;
                     // logic for when patterns played > patterns played in song (current_pattern_num = start index).
                     if(current_pattern_num >= num_pat_played)
                     {
                     	current_pattern_num = pattern_reloop_index;
                     }
                     current_pattern = pattern_table[current_pattern_num];
                  }
                  if(ch4effect == PATTERN_BREAK)
                  {
                  	var next_pattern_row = 10*Math.floor(ch4effect_value / 16) + (ch4effect_value % 16);
                  	current_row = next_pattern_row - 1; // because current_row++ when moving to next row.
                     current_pattern_num++;
                     // logic for when patterns played > patterns played in song (current_pattern_num = start index).
                     if(current_pattern_num >= num_pat_played)
                     {
                     	current_pattern_num = pattern_reloop_index;
                     }
                     current_pattern = pattern_table[current_pattern_num];
                  }
                  
                  // SET SPEED EFFECT
                  if(ch1effect == SET_SPEED)
						{
							ticksPerRow = ch1effect_value;
						}
						if(ch2effect == SET_SPEED)
						{
							ticksPerRow = ch2effect_value;
						}
						if(ch3effect == SET_SPEED)
						{
							ticksPerRow = ch3effect_value;
						}
						if(ch4effect == SET_SPEED)
						{
							ticksPerRow = ch4effect_value;
						}
						samples_per_row_next = ticksPerRow*TIME_PER_TICK_SECONDS*sampleRate;	// 5292 for song speed 6.
						
						// SET VOL EFFECT
						if(ch1effect == SET_VOLUME)
						{
							ch1_vol = ch1effect_value;
							if(ch1_vol > 64)
								ch1_vol = 64;
						}
						if(ch2effect == SET_VOLUME)
						{
							ch2_vol = ch2effect_value;
							if(ch2_vol > 64)
								ch2_vol = 64;
						}
						if(ch3effect == SET_VOLUME)
						{
							ch3_vol = ch3effect_value;
							if(ch3_vol > 64)
								ch3_vol = 64;
						}
						if(ch4effect == SET_VOLUME)
						{
							ch4_vol = ch4effect_value;
							if(ch4_vol > 64)
								ch4_vol = 64;
						}
               }

               if(ch1_on)
               {
                  if(((ch1SampPos < ch1sampLength_rs) && !ch1_looping) || (ch1_looping && (ch1SampPos < ch1_re_rs)))
                  {
                     ch1 = samples_resampled_ch1[ch1SampPos++];
                     ch1 = ch1 * (sample_vols[ch1samp - 1]/64);
                     ch1 = ch1 * (ch1_vol/64);
                  }
                  else
                  {
							if(ch1_loops)
							{
								ch1_looping = 1;

								ch1SampPos = ch1_ro_rs;
		                  ch1 = samples_resampled_ch1[ch1SampPos++];
		                  ch1 = ch1 * (sample_vols[ch1samp - 1]/64);
		                  ch1 = ch1 * (ch1_vol/64);
							}
							else
							{
								ch1 = 0;
							}
                  }
               }
               if(ch2_on)
               {
                  if(((ch2SampPos < ch2sampLength_rs) && !ch2_looping) || (ch2_looping && (ch2SampPos < ch2_re_rs)))
                  {
                     ch2 = samples_resampled_ch2[ch2SampPos++];
                     ch2 = ch2 * (sample_vols[ch2samp - 1]/64);
                     ch2 = ch2 * (ch2_vol/64);
                  }
                  else
                  {
							if(ch2_loops)
							{
								ch2_looping = 1;

								ch2SampPos = ch2_ro_rs;
		                  ch2 = samples_resampled_ch2[ch2SampPos++];
		                  ch2 = ch2 * (sample_vols[ch2samp - 1]/64);
		                  ch2 = ch2 * (ch2_vol/64);
							}
							else
							{
								ch2 = 0;
							}
                  }
               }
               if(ch3_on)
               {
                  if(((ch3SampPos < ch3sampLength_rs) && !ch3_looping) || (ch3_looping && (ch3SampPos < ch3_re_rs)))
                  {
                     ch3 = samples_resampled_ch3[ch3SampPos++];
                     ch3 = ch3 * (sample_vols[ch3samp - 1]/64);
                     ch3 = ch3 * (ch3_vol/64);
                  }
                  else
                  {
							if(ch3_loops)
							{
								ch3_looping = 1;

								ch3SampPos = ch3_ro_rs;
		                  ch3 = samples_resampled_ch3[ch3SampPos++];
		                  ch3 = ch3 * (sample_vols[ch3samp - 1]/64);
		                  ch3 = ch3 * (ch3_vol/64);
							}
							else
							{
								ch3 = 0;
							}
                  }
               }
               if(ch4_on)
               {
                  if(((ch4SampPos < ch4sampLength_rs) && !ch4_looping) || (ch4_looping && (ch4SampPos < ch4_re_rs)))
                  {
                     ch4 = samples_resampled_ch4[ch4SampPos++];
                     ch4 = ch4 * (sample_vols[ch4samp - 1]/64);
                     ch4 = ch4 * (ch4_vol/64);
                  }
                  else
                  {
							if(ch4_loops)
							{
								ch4_looping = 1;

								ch4SampPos = ch4_ro_rs;
		                  ch4 = samples_resampled_ch4[ch4SampPos++];
		                  ch4 = ch4 * (sample_vols[ch4samp - 1]/64);
		                  ch4 = ch4 * (ch4_vol/64);
							}
							else
							{
								ch4 = 0;
							}
                  }
               }
               
               samples_done++;

               soundDataL[i] = 1.5*(ch1/(4*128) + ch4/(4*128)) + 0.5*(ch2/(4*128) + ch3/(4*128));
               soundDataR[i] = 1.5*(ch2/(4*128) + ch3/(4*128)) + 0.5*(ch1/(4*128) + ch4/(4*128));
            }
         }

         // credit of iOS function: stackoverflow.com/questions/9038625/detect-if-device-is-ios
//         function iOS()
//         {
//           return [
//             'iPad Simulator',
//             'iPhone Simulator',
//             'iPod Simulator',
//             'iPad',
//             'iPhone',
//             'iPod'
//           ].includes(navigator.platform)
//           // iPad on iOS 13 detection
//           || (navigator.userAgent.includes(\"Mac\") && \"ontouchend\" in document)
//         }

         function instance()
			{
//            if(iOS())
//            {
//               audioDestination = new AudioDataDestination(sampleRate, requestSoundData);
//            }
//            else
            if(page_loaded && !playing) // temp remove to test on iOS
				{
               playing = true;
					audioDestination = new AudioDataDestination(sampleRate, requestSoundData);
				}
			}

         function start()
         {
            ch1SampPos = 0; ch2SampPos = 0; ch3SampPos = 0; ch4SampPos = 0;
         }

         function stop()
         {
            ch1samp = 0; ch2samp = 0; ch3samp = 0; ch4samp = 0;
            ch1period = 0; ch2period = 0; ch3period = 0; ch4period = 0;
            ch1effect = 0; ch2effect = 0; ch3effect = 0; ch4effect = 0;
         	ch1effect_value = 0; ch2effect_value = 0; ch3effect_value = 0; ch4effect_value = 0;
         	ch1_vol = 64; ch2_vol = 64; ch3_vol = 64; ch4_vol = 64;
            ch1sampLength = 0; ch2sampLength = 0; ch3sampLength = 0; ch4sampLength = 0;
            ch1sampLength_rs = 0; ch2sampLength_rs = 0; ch3sampLength_rs = 0; ch4sampLength_rs = 0;
            ch1SampPos = 0; ch2SampPos = 0; ch3SampPos = 0; ch4SampPos = 0;
            
            ch1 = 0; ch2 = 0; ch3 = 0; ch4 = 0;
            ch1_on = 0; ch2_on = 0; ch3_on = 0; ch4_on = 0;
            
            current_row = 0;
            samples_done = 0;
            
            current_pattern_num = 0;
            current_pattern = pattern_table[current_pattern_num];

            ch1samp = patterns[current_pattern][current_row][1].sample;
            ch2samp = patterns[current_pattern][current_row][2].sample;
            ch3samp = patterns[current_pattern][current_row][3].sample;
            ch4samp = patterns[current_pattern][current_row][4].sample;

            ch1period = patterns[current_pattern][current_row][1].period;
            ch2period = patterns[current_pattern][current_row][2].period;
            ch3period = patterns[current_pattern][current_row][3].period;
            ch4period = patterns[current_pattern][current_row][4].period;
            
            ch1effect = patterns[current_pattern][current_row][1].effect;
            ch2effect = patterns[current_pattern][current_row][2].effect;
            ch3effect = patterns[current_pattern][current_row][3].effect;
            ch4effect = patterns[current_pattern][current_row][4].effect;
            
            ch1effect_value = patterns[current_pattern][current_row][1].effect_value;
            ch2effect_value = patterns[current_pattern][current_row][2].effect_value;
            ch3effect_value = patterns[current_pattern][current_row][3].effect_value;
            ch4effect_value = patterns[current_pattern][current_row][4].effect_value;

            if(ch1samp != 0)
               ch1sampLength = sample_lengths[ch1samp-1];
            if(ch2samp != 0)
               ch2sampLength = sample_lengths[ch2samp-1];
            if(ch3samp != 0)
               ch3sampLength = sample_lengths[ch3samp-1];
            if(ch4samp != 0)
               ch4sampLength = sample_lengths[ch4samp-1];
            
            if(ch1period != 0)
				{
					ch1_on = 1;
					var resample_freq = resample_playback_rate(ch1period);
					resample(samples_resampled_ch1, samples, ch1samp, sampleRate, resample_freq, ch1sampLength);
					ch1sampLength_rs = Math.ceil(ch1sampLength*sampleRate/resample_freq);
				}
		
				if(ch2period != 0)
				{
					ch2_on = 1;
					var resample_freq = resample_playback_rate(ch2period);
					resample(samples_resampled_ch2, samples, ch2samp, sampleRate, resample_freq, ch2sampLength);
					ch2sampLength_rs = Math.ceil(ch2sampLength*sampleRate/resample_freq);
				}
		
				if(ch3period != 0)
				{
					ch3_on = 1;
					var resample_freq = resample_playback_rate(ch3period);
					resample(samples_resampled_ch3, samples, ch3samp, sampleRate, resample_freq, ch3sampLength);
					ch3sampLength_rs = Math.ceil(ch3sampLength*sampleRate/resample_freq);
				}
		
				if(ch4period != 0)
				{
					ch4_on = 1;
					var resample_freq = resample_playback_rate(ch4period);
					resample(samples_resampled_ch4, samples, ch4samp, sampleRate, resample_freq, ch4sampLength);
					ch4sampLength_rs = Math.ceil(ch4sampLength*sampleRate/resample_freq);
				}
            
            ticksPerRow = 6; //equals song speed and used for samples per row.
	      	if(ch1effect == SET_SPEED)
				{
					ticksPerRow = ch1effect_value;
				}
				if(ch2effect == SET_SPEED)
				{
					ticksPerRow = ch2effect_value;
				}
				if(ch3effect == SET_SPEED)
				{
					ticksPerRow = ch3effect_value;
				}
				if(ch4effect == SET_SPEED)
				{
					ticksPerRow = ch4effect_value;
				}
				samples_per_row = ticksPerRow*TIME_PER_TICK_SECONDS*sampleRate;	// 5292 for song speed 6.
				samples_per_row_next = samples_per_row;
				
				if(ch1effect == SET_VOLUME)
				{
					ch1_vol = ch1effect_value;
					if(ch1_vol > 64)
						ch1_vol = 64;
				}
				if(ch2effect == SET_VOLUME)
				{
					ch2_vol = ch2effect_value;
					if(ch2_vol > 64)
						ch2_vol = 64;
				}
				if(ch3effect == SET_VOLUME)
				{
					ch3_vol = ch3effect_value;
					if(ch3_vol > 64)
						ch3_vol = 64;
				}
				if(ch4effect == SET_VOLUME)
				{
					ch4_vol = ch4effect_value;
					if(ch4_vol > 64)
						ch4_vol = 64;
				}
         }
</script>
             \n\n";

   // display stuff, hide some with CSS, make <span>'s for DOM data structure.

   echo "<div id=songname>Song title: <span id=song_title>{$song_title}</span></div>\n";

   echo "<div id=modtype>MOD Format: <span id=mod_type>{$mod_type}</span></div>\n";
   
   $i = 1;
   foreach($sample_names as $sample_name)
   {
      echo "<div class=samplename>Sample {$i} Name: <span id=s{$i}_name>{$sample_name}</span></div>\n";
      $i++;
   }
   $i = 1;
   foreach($sample_lengths as $sample_length)
   {
      echo "<div class=samplelength>Sample {$i} Length: <span id=s{$i}_length>{$sample_length}</span></div>\n";
      $i++;
   }
   $i = 1;
   foreach($sample_finetunes as $sample_finetune)
   {
      echo "<div class=samplefinetune>Sample {$i} Finetune: <span id=s{$i}_finetune>{$sample_finetune}</span></div>\n";
      $i++;
   }
   $i = 1;
   foreach($sample_vols as $sample_vol)
   {
      echo "<div class=samplevolume>Sample {$i} Volume: <span id=s{$i}_vol>{$sample_vol}</span></div>\n";
      $i++;
   }
   $i = 1;
   foreach($sample_repeat_offsets as $sample_ro)
   {
      echo "<div class=samplero>Sample {$i} Repeat Offset: <span id=s{$i}_ro>{$sample_ro}</span></div>\n";
      $i++;
   }
   $i = 1;
   foreach($sample_repeat_lengths as $sample_rl)
   {
      echo "<div class=samplerl>Sample {$i} Repeat Length: <span id=s{$i}_rl>{$sample_rl}</span></div>\n";
      $i++;
   }

   echo "<div id=numpatternsplayed>Number of patterns played: <span id=num_patterns_played>{$num_patterns}</span></div>\n";
   echo "<div id=songendjumppos>Song end jump position: <span id=song_end_jump_pos>{$song_end_jump_pos}</span></div>\n";

   for($i = 0; $i < $num_patterns; $i++)
   {
      echo "<div class=pattern_table>Pattern table entry {$i}: <span id=p{$i}_num>{$pattern_order[$i]}</span></div>\n";
   }

   //ready to collect patterns, followed by samples
   //when extracting 2s compliment 8-bit values (-128 to 127), then just get the number as above, but then subtract 256 if > 127.

   echo "<div id=num_patterns_in_file>{$num_patterns_in_file}</div>";
   for($p = 0; $p < $num_patterns_in_file; $p++)
   {
      echo "<div id=pattern{$p}>\n";
      for($row = 0; $row < 64; $row++)
      {
         echo "<div class=a_row id=p{$p}r{$row}>";
         for($ch = 1; $ch <= 4; $ch++)
         {
            $note = bin2hex(fread($mod_file, 4));
            $period = (16*16)*hex2dec($note[1]) + (16)*hex2dec($note[2]) + hex2dec($note[3]);
            $sample = 16*hex2dec($note[0]) + hex2dec($note[4]);
            if(hex2dec($note[5]) == 14) //E
            {
            	$effect = 16*hex2dec($note[5]) + hex2dec($note[6]);
            	$effect_value = hex2dec($note[7]);
            }
            else
            {
            	$effect = hex2dec($note[5]);
            	$effect_value = 16*hex2dec($note[6]) + hex2dec($note[7]);
            }
            echo "Ch{$ch}: Period: <span id=p{$p}r{$row}ch{$ch}period>{$period}</span> Sample #: <span id=p{$p}r{$row}ch{$ch}sample>{$sample}</span> Effect: <span id=p{$p}r{$row}ch{$ch}effect>{$effect}</span> EffectValue: <span id=p{$p}r{$row}ch{$ch}effectvalue>{$effect_value}</span> ";
         }
         echo "</div>\n";
      }
      echo "</div>\n";
   }

   #$number_of_samples = 0;  //with data, not all 31.
   for($s = 1; $s <= 31; $s++)
   {
      if($sample_lengths[$s-1] != 0)
      {
         #$number_of_samples++;
         $bytearray = "";
         echo "<div id=sample_data_{$s} class=sampledata>";
         for($b = 0; $b < $sample_lengths[$s-1]; $b++)
         {
            $byte = bin2hex(fread($mod_file, 1));
            $byte = hex2dec($byte[1]) + 16*hex2dec($byte[0]);
            if($byte > 127)
               $byte -= 256;
            $bytearray .= $byte.".";
         }
         $bytearray[strlen($bytearray) - 1] = "\n";
         echo $bytearray."</div>\n";
      }
   }
   echo "Number of samples: <div id=number_of_samples>{$number_of_samples}</div>\n";

   fclose($mod_file);





   echo "</body></html>\n";
   
}


function hex2dec($hex)
{
   if(($hex == "A") || ($hex == "a")) return 10;
   elseif(($hex == "B") || ($hex == "b")) return 11;
   elseif(($hex == "C") || ($hex == "c")) return 12;
   elseif(($hex == "D") || ($hex == "d")) return 13;
   elseif(($hex == "E") || ($hex == "e")) return 14;
   elseif(($hex == "F") || ($hex == "f")) return 15;
   else return $hex;
}

