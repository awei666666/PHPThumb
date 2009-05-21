<?php
/**
 * PhpThumb GD Thumb Class Definition File
 * 
 * This file contains the definition for the GdThumb object
 * 
 * @author Ian Selby <ian@gen-x-design.com>
 * @copyright Copyright 2009 Gen X Design
 * @version 3.0
 * @package PhpThumb
 * @filesource
 */

/**
 * GdThumb Class Definition
 * 
 * This is the GD Implementation of the PHP Thumb library.
 * 
 * @package PhpThumb
 * @subpackage Core
 */
class GdThumb extends ThumbBase
{
	/**
	 * The prior image (before manipulation)
	 * 
	 * @var resource
	 */
	protected $oldImage;
	/**
	 * The new image (after manipulation)
	 * 
	 * @var resource
	 */
	protected $newImage;
	/**
	 * The working image (used during manipulation)
	 * 
	 * @var resource
	 */
	protected $workingImage;
	/**
	 * The current dimensions of the image
	 * 
	 * @var array
	 */
	protected $currentDimensions;
	/**
	 * The new, calculated dimensions of the image
	 * 
	 * @var array
	 */
	protected $newDimensions;
	/**
	 * The options for this class
	 * 
	 * This array contains various options that determine the behavior in
	 * various functions throughout the class.  Functions note which specific 
	 * option key / values are used in their documentation
	 * 
	 * @var array
	 */
	protected $options;
	/**
	 * The maximum width an image can be after resizing (in pixels)
	 * 
	 * @var int
	 */
	protected $maxWidth;
	/**
	 * The maximum height an image can be after resizing (in pixels)
	 * 
	 * @var int
	 */
	protected $maxHeight;
	/**
	 * The percentage to resize the image by
	 * 
	 * @var int
	 */
	protected $percent;
	
	/**
	 * Class Constructor
	 * 
	 * @return GdThumb 
	 * @param string $fileName
	 */
	public function __construct ($fileName, $options = array())
	{
		parent::__construct($fileName);
		
		$this->determineFormat();
		$this->verifyFormatCompatiblity();
		
		switch ($this->format)
		{
			case 'GIF':
				$this->oldImage = imagecreatefromgif($this->fileName);
				break;
			case 'JPG':
				$this->oldImage = imagecreatefromjpeg($this->fileName);
				break;
			case 'PNG':
				$this->oldImage = imagecreatefrompng($this->fileName);
				break;
		}
		
		$size = getimagesize($this->fileName);
		$this->currentDimensions = array
		(
			'width' 	=> $size[0],
			'height'	=> $size[1]
		);
		
		$this->newImage = $this->oldImage;
		
		$this->setOptions($options);
		
		// TODO: Port gatherImageMeta to a separate function that can be called to extract exif data
	}
	
	/**
	 * Class Destructor
	 * 
	 */
	public function __destruct ()
	{
		if (is_resource($this->newImage))
		{
			imagedestroy($this->newImage);
		}
		
		if (is_resource($this->oldImage))
		{
			imagedestroy($this->oldImage);
		}
		
		if (is_resource($this->workingImage))
		{
			imagedestroy($this->workingImage);
		}
	}
	
	##############################
	# ----- API FUNCTIONS ------ #
	##############################
	
	/**
	 * Resizes an image to be no larger than $maxWidth or $maxHeight
	 * 
	 * If either param is set to zero, then that dimension will not be considered as a part of the resize.
	 * Additionally, if $this->options['resizeUp'] is set to true (false by default), then this function will
	 * also scale the image up to the maximum dimensions provided.
	 * 
	 * @param int $maxWidth The maximum width of the image in pixels
	 * @param int $maxHeight The maximum height of the image in pixels
	 */
	public function resize ($maxWidth = 0, $maxHeight = 0)
	{
		// make sure our arguments are valid
		if (!is_numeric($maxWidth))
		{
			throw new InvalidArgumentException('$maxWidth must be numeric');
		}
		
		if (!is_numeric($maxHeight))
		{
			throw new InvalidArgumentException('$maxHeight must be numeric');
		}
		
		// make sure we're not exceeding our image size if we're not supposed to
		if ($this->options['resizeUp'] === false)
		{
			$this->maxHeight	= (intval($maxHeight) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $maxHeight;
			$this->maxWidth		= (intval($maxWidth) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $maxWidth;
		}
		else
		{
			$this->maxHeight	= intval($maxHeight);
			$this->maxWidth		= intval($maxWidth);
		}
		
		// get the new dimensions...
		$this->calcImageSize($this->currentDimensions['width'], $this->currentDimensions['height']);
		
		// create the working image
		if (function_exists('imagecreatetruecolor'))
		{
			$this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
		}
		else
		{
			$this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
		}
		
		// preserve alpha transparency - originally suggested by Aimi :)
		if ($this->format == 'PNG')
		{
			imagealphablending($this->workingImage, false);
			$colorTransparent = imagecolorallocatealpha($this->workingImage, 255, 255, 255, 0);
			imagefill($this->workingImage, 0, 0, $colorTransparent);
			imagesavealpha($this->workingImage, true);
		}
		// preserve transparency in GIFs... this is usually pretty rough tho
		if ($this->format == 'GIF')
		{
			$colorTransparent = imagecolorallocate($this->workingImage, 0, 0, 0);
			imagecolortransparent($this->workingImage, $colorTransparent);
			imagetruecolortopalette($this->workingImage, true, 256);
		}
		
		// and create the newly sized image
		imagecopyresampled
		(
			$this->workingImage,
			$this->oldImage,
			0,
			0,
			0,
			0,
			$this->newDimensions['newWidth'],
			$this->newDimensions['newHeight'],
			$this->currentDimensions['width'],
			$this->currentDimensions['height']
		);

		// update all the variables and resources to be correct
		$this->oldImage 					= $this->workingImage;
		$this->newImage 					= $this->workingImage;
		$this->currentDimensions['width'] 	= $this->newDimensions['newWidth'];
		$this->currentDimensions['height'] 	= $this->newDimensions['newHeight'];
	}
	
	/**
	 * Shows or saves an image
	 * 
	 * Technically, you wouldn't want to use this function to save an image (use $this->save() instead), but 
	 * you could.  Anyway, this function will show the current image by first sending the appropriate header
	 * for the format, and then outputting the image data.  Otherwise, if a name is provided, we'll save the 
	 * image to that location.
	 * 
	 * @param string $name The full path to the file and the filename to save
	 */
	public function show ($name = null) 
	{
		switch ($this->format) 
		{
			case 'GIF':
				if ($name !== null) 
				{
					imagegif($this->newImage, $name);
				}
				else 
				{
					header('Content-type: image/gif');
					imagegif($this->newImage);
				}
				break;
			case 'JPG':
				if ($name !== null) 
				{
					imagejpeg($this->newImage, $name, $this->options['jpegQuality']);
				}
				else 
				{
					header('Content-type: image/jpeg');
					imagejpeg($this->newImage, null, $this->options['jpegQuality']);
				}
				break;
			case 'PNG':
				if ($name !== null) 
				{
					imagepng($this->newImage, $name);
				}
				else 
				{
					header('Content-type: image/png');
					imagepng($this->newImage);
				}
				break;
		}
	}
	
	/**
	 * Saves an image
	 * 
	 * This function will make sure the target directory is writeable, and then save the image.
	 * 
	 * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
	 * is set as an option ($this->options['correctPermissions']).  If the target cannot be made writeable, then a
	 * RuntimeException is thrown.
	 * 
	 * @param string $fileName The full path and filename of the image to save
	 */
	public function save ($fileName)
	{
		// make sure the directory is writeable
		if (!is_writeable(dirname($fileName)))
		{
			// try to correct the permissions
			if ($this->options['correctPermissions'] === true)
			{
				@chmod(dirname($fileName), 0777);
				
				// throw an exception if not writeable
				if (!is_writeable(dirname($fileName)))
				{
					throw new RuntimeException ('File is not writeable, and could not correct permissions: ' . $fileName);
				}
			}
			// throw an exception if not writeable
			else
			{
				throw new RuntimeException ('File not writeable: ' . $fileName);
			}
		}
		
		$this->show($fileName);
	}
	
	#################################
	# ----- GETTERS / SETTERS ----- #
	#################################
	
	/**
	 * Sets $this->options to $options
	 * 
	 * @param array $options
	 */
	public function setOptions ($options = array())
	{
		// make sure we've got an array for $this->options (could be null)
		if (!is_array($this->options))
		{
			$this->options = array();
		}
		
		// make sure we've gotten a proper argument
		if (!is_array($options))
		{
			throw new InvalidArgumentException ('setOptions requires an array');
		}
		
		// we've yet to init the default options, so create them here
		if (sizeof($this->options) == 0)
		{
			$defaultOptions = array 
			(
				'resizeUp'				=> false,
				'jpegQuality'			=> 100,
				'correctPermissions'	=> true
			);
		}
		// otherwise, let's use what we've got already
		else
		{
			$defaultOptions = $this->options;
		}
		
		$this->options = array_merge($defaultOptions, $options);
	}
	
	#################################
	# ----- UTILITY FUNCTIONS ----- #
	#################################
	
	/**
	 * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
	 * 
	 * @return array 
	 * @param int $width
	 * @param int $height
	 */
	protected function calcWidth ($width, $height)
	{
		$newWidthPercentage	= (100 * $this->maxWidth) / $width;
		$newHeight			= ($height * $newWidthPercentage) / 100;
		
		return array
		(
			'newWidth'	=> intval($this->maxWidth),
			'newHeight'	=> intval($newHeight)
		);
	}
	
	/**
	 * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
	 * 
	 * @return array 
	 * @param int $width
	 * @param int $height
	 */
	protected function calcHeight ($width, $height)
	{
		$newHeightPercentage	= (100 * $this->maxHeight) / $height;
		$newWidth 				= ($width * $newHeightPercentage) / 100;
		
		return array
		(
			'newWidth'	=> intval($newWidth),
			'newHeight'	=> intval($this->maxHeight)
		);
	}
	
	/**
	 * Calculates a new width and height for the image based on $this->percent and the provided dimensions
	 * 
	 * @return array 
	 * @param int $width
	 * @param int $height
	 */
	protected function calcPercent ($width, $height)
	{
		$newWidth	= ($width * $this->percent) / 100;
		$newHeight	= ($height * $this->percent) / 100;
		
		return array 
		(
			'newWidth'	=> intval($newWidth),
			'newHeight'	=> intval($newHeight)
		);
	}
	
	/**
	 * Calculates the new image dimensions
	 * 
	 * These calculations are based on both the provided dimensions and $this->maxWidth and $this->maxHeight
	 * 
	 * @param int $width
	 * @param int $height
	 */
	protected function calcImageSize ($width, $height)
	{
		$newSize = array
		(
			'newWidth'	=> $width,
			'newHeight'	=> $height
		);
		
		if ($this->maxWidth > 0)
		{
			$newSize = $this->calcWidth($width, $height);
			
			if ($this->maxHeight > 0 && $newSize['newHeight'] > $this->maxHeight)
			{
				$newSize = $this->calcHeight($newSize['newWidth'], $newSize['newHeight']);
			}
		}
		
		if ($this->maxHeight > 0)
		{
			$newSize = $this->calcHeight($width, $height);
			
			if ($this->maxWidth > 0 && $newSize['newWidth'] > $this->maxWidth)
			{
				$newSize = $this->calcWidth($newSize['newWidth'], $newSize['newHeight']);
			}
		}
		
		$this->newDimensions = $newSize;
	}
	
	/**
	 * Calculates new dimensions based on $this->percent and the provided dimensions
	 * 
	 * @param int $width
	 * @param int $height
	 */
	protected function calcImageSizePercent ($width, $height)
	{
		if ($this->percent > 0)
		{
			$this->newDimensions = $this->calcPercent($width, $height);
		}
	}
	
	/**
	 * Determines the file format by mime-type
	 * 
	 * This function will throw exceptions for invalid images / mime-types
	 * 
	 */
	protected function determineFormat ()
	{
		$formatInfo = getimagesize($this->fileName);
		
		// non-image files will return false
		if ($formatInfo === false)
		{
			$this->triggerError('File is not a valid image: ' . $this->fileName);
		}
		
		$mimeType = isset($formatInfo['mime']) ? $formatInfo['mime'] : null;
		
		switch ($mimeType)
		{
			case 'image/gif':
				$this->format = 'GIF';
				break;
			case 'image/jpeg':
				$this->format = 'JPG';
				break;
			case 'image/png':
				$this->format = 'PNG';
				break;
			default:
				$this->triggerError('Image format not supported: ' . $mimeType);
		}
	}
	
	/**
	 * Makes sure the correct GD implementation exists for the file type
	 * 
	 */
	protected function verifyFormatCompatiblity ()
	{
		$isCompatible 	= true;
		$gdInfo			= gd_info();
		
		switch ($this->format)
		{
			case 'GIF':
				$isCompatible = $gdInfo['GIF Create Support'];
				break;
			case 'JPG':
			case 'PNG':
				$isCompatible = $gdInfo[$this->format . ' Support'];
				break;
			default:
				$isCompatible = false;
		}
		
		if (!$isCompatible)
		{
			$this->triggerError('Your GD installation does not support ' . $this->format . ' image types');	
		}
	}
}
