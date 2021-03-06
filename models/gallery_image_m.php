<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 *
 * The galleries module enables users to create albums, upload photos and manage their existing albums.
 *
 * @author 		PyroCMS Dev Team
 * @package 	PyroCMS
 * @subpackage 	Gallery Module
 * @category 	Modules
 * @license 	Apache License v2.0
 */
class Gallery_image_m extends MY_Model
{
	/**
	 * Constructor method
	 * 
	 * @author PyroCMS Dev Team
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Get all gallery images in a folder
	 *
	 * @author PyroCMS Dev Team
	 * @access public
	 * @param int $id The ID of the gallery
	 * @param array $options Options
	 * @return mixed
	 */
	public function get_images_by_gallery($id, $options = array())
	{
		// Find new images on files
		$this->set_new_image_files($id);	

		// Clear old files on images
		$this->unset_old_image_files($id);

		if (isset($options['offset']))
		{
			$this->db->offset($options['offset']);
		}

		if (isset($options['limit']))
		{
			$this->db->limit($options['limit']);
		}

		// Grand finale, do what you gotta do!!
		$images = $this->db
				// Select fields on gallery images table
				->select('gallery_images.*, files.name, files.filename, files.extension, files.description, files.name as title, galleries.folder_id, galleries.slug as gallery_slug, galleries.title as gallery_title')
				// Set my gallery by id
				->where('galleries.id', $id)
				// Filter images from my gallery
				->join('galleries', 'galleries.id = gallery_images.gallery_id', 'left')
				// Filter from my images
				->join('files', 'files.id = gallery_images.file_id', 'left')
				// Filter files type image
				->where('files.type', 'i')
				// Order by user order
				->order_by('`order`', 'asc')
				// Get all!
				->get('gallery_images')
				->result();

		return $images;
	}

	public function set_new_image_files($gallery_id = 0)
	{
		$this->db
			// Select fields on files table
			->select('files.id as file_id, galleries.id as gallery_id')
			->from('files')
			// Filter from my gallery folder
			->join('galleries', 'galleries.folder_id = files.folder_id', 'left')
			// Join with the existing
			->join('gallery_images', 'gallery_images.file_id = files.id', 'left')
			// Set my gallery by id
			->where(array('files.type' => 'i', 'galleries.id' => $gallery_id))
			// This will be one frustrated join. Sorry pal!
			->where('gallery_images.file_id IS NULL', null, FALSE);
	
		// Already updated, nothing to do here..
		if ( ! $new_images = $this->db->get()->result_array())
		{
			return FALSE;
		}	

		// Get the max order
		$max_order = $this->db
			->select_max('`order`')
			->get_where('gallery_images', array('gallery_id' => $gallery_id))->row();		
		
		// Insert new images, increasing the order
		$insert_images = array();

		foreach ($new_images as &$new_image)
		{
			$new_image['order'] = ++$max_order->order;
		}				
		
		unset($new_image);

		$this->db->insert_batch('gallery_images', $new_images);
		
		return TRUE;
	}

	public function unset_old_image_files($gallery_id = 0)
	{
		$not_in = array();

		// Get all image from folder of my gallery...
		$images = $this->db
			->select('files.id')
			->from('files')
			->join('galleries', 'galleries.folder_id = files.folder_id')
			->where('files.type', 'i')
			->where('galleries.id', $gallery_id)
			->get()
			->result();

		if (count($images) > 0)
		{
			foreach ($images AS $item)
			{
				$not_in[] = $item->id;
			}
		
			$this->db
				// Select fields on gallery images table
				->select('gallery_images.id')
				->from('gallery_images')
				// Set my gallery by id
				->where('galleries.id', $gallery_id)
				// Filter images from my gallery
				->join('galleries', 'galleries.id = gallery_images.gallery_id')
				// Get all images that are no longer in a gallery
				->where_not_in('file_id', $not_in);
	
			// Already updated, nothing to do here..
			if ( ! $old_images = $this->db->get()->result())
			{
				return FALSE;
			}

			// Remove missing files images
			foreach ($old_images as $old_image)
			{
				$this->gallery_image_m->delete($old_image->id);
			}
		}

		return TRUE;
	}
	
	/**
	 * Preview images from folder
	 *
	 * @author Jerel Unruh - PyroCMS Dev Team
	 * @access public
	 * @param int $id The ID of the folder
	 * @param array $options Options
	 * @return mixed
	 */
	public function get_images_by_file_folder($id, $options = array())
	{

		if (isset($options['offset']))
		{
			$this->db->limit($options['offset']);
		}

		if (isset($options['limit']))
		{
			$this->db->limit($options['limit']);
		}

		// Grand finale, do what you gotta do!!
		$images = $this->db
				->select('files.*')
				->where('folder_id', $id)
				->where('files.type', 'i')
				->get('files')
				->result();

		return $images;
	}
	
	/**
	 * Get an image along with the gallery slug
	 * 
	 * @author PyroCMS Dev Team
	 * @access public
	 * @param int $id The ID of the image
	 * @return mixed
	 */
	public function get($id)
	{
		$query = $this->db
			->select('gallery_images.*, files.name, files.filename, files.extension, files.description, files.name as title, galleries.folder_id, galleries.slug as gallery_slug')
			->join('galleries', 'gallery_images.gallery_id = galleries.id', 'left')
			->join('files', 'files.id = gallery_images.file_id', 'left')
			->where('gallery_images.id', $id)
			->get('gallery_images');
				
		if ( $query->num_rows() > 0 )
		{
			return $query->row();
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Dropdown
	 *
	 * @param string $key
	 * @param string $value
	 * @return mixed
	 */
	public function dropdown($key, $value)
	{
		$dropdown = array();

		$query = $this->select(array($key, $value))
			->join('files', 'files.id = gallery_images.file_id', 'left')
			->get_all();
				
		if ( count($query) > 0 )
		{
			if (strpos($key, '.')) $key = substr($key, (strpos($key, '.') + 1));
			if (strpos($value, '.')) $value = substr($value, (strpos($value, '.') + 1));

			foreach ($query AS $thumbnail) 
			{
				$dropdown[$thumbnail->{$key}] = $thumbnail->{$value};
			}

			return $dropdown;
		}
		else
		{
			return FALSE;
		}
	}
}