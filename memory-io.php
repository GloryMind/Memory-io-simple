<?php

//**********************************
interface MemoryRawIO {
	public function GetSize();
	public function IncreaseSize( $size );
	public function ReduceSize( $size );
	public function ReadBuffer( $offset, $size );
	public function WriteBuffer( $offset, $data );
}

//**********************************
interface MemoryReaderIO {
	public function ReadInt8($offset);
	public function ReadInt16($offset);
	public function ReadInt32($offset);
	
	public function WriteInt8($offset, $data);
	public function WriteInt16($offset, $data);
	public function WriteInt32($offset, $data);
	
	public function ReadUInt8($offset);
	public function ReadUInt16($offset);
	public function ReadUInt32($offset);
	
	public function WriteUInt8($offset, $data);
	public function WriteUInt16($offset, $data);
	public function WriteUInt32($offset, $data);

	public function ReadBuffer($offset, $size);
	public function WriteBuffer($offset, $data);
}

//**********************************
interface MemoryManagerIO {
	public function Allocate( $size );
	public function Free( $ptr );
}


//**********************************
//**********************************
//**********************************

//**********************************
class File_MemoryRawIO implements MemoryRawIO {
	private $mn;
	private $size;
	
	public function __construct( FileManager $mn ) {
		$this->mn = $mn;
		$this->size = $this->mn->GetSize();
	}
	
	
	public function GetSize() {
		return $this->size;
	}
	public function IncreaseSize( $size ) {
		$offset = $this->size;
		$this->size += $size;
		$this->mn->SetSize( $this->size );
		$this->WriteBuffer($offset , str_repeat("\0", $size) );
	}
	public function ReduceSize( $size ) {
		$this->size -= $size;
		if ( $this->size < 0 ) {
			$this->size = 0;
		}
		$this->mn->SetSize( $this->size );
	}


	public function ReadBuffer( $offset, $size ) {
		$this->TryOffsetSize($offset, $size);
		return $this->mn->Read($offset, $size);
	}
	public function WriteBuffer( $offset, $data ) {
		$this->TryOffsetSize($offset, strlen($data));
		return $this->mn->Write($offset, $data);
	}


	private function TryOffsetSize($offset, $size) {
		if ( $offset < 0 ) {
			throw new \Exception("Offset: {$offset} < 0");
		}
		if ( $offset + $size > $this->size ) {
			throw new \Exception("Offset + size > real size: {$offset} + {$size} > {$this->size}");
		}
	}
}

//**********************************
class MemoryReader implements MemoryReaderIO {
	private $memory;
	private $x32;
	private $x64;
	private $_8;
	private $_16;
	private $_24;
	private $_32;
	private $_64;
	private $_i8;
	private $_i16;
	private $_i24;
	private $_i32;
	private $_i64;

	public function __construct( MemoryRawIO $memory ) {
		$this->memory = $memory;
		
		$this->x64 = !$this->x32 = is_float(4294967297);
		foreach([8,16,24,32,/*64*/] as $bits) {
			$this->{"_{$bits}"} = 1 << ($bits-1);
		}
		foreach([8,16,24,32,/*64*/] as $bits) {
			$this->{"_i{$bits}"} = -1 >> $bits << $bits;
		}
		if ( $this->x32 ) { $this->_i32 = 0; }
		if ( $this->x64 ) { $this->_i64 = 0; }
	}


	public function ReadUInt8($offset) {
		$bin = $this->memory->ReadBuffer($offset,1);
		return ord($bin[0]);
	}
	public function ReadUInt16($offset) {
		$bin = $this->memory->ReadBuffer($offset,2);
		return (ord($bin[0])) | (ord($bin[1]) <<  8);
	}
	public function ReadUInt32($offset) {
		$bin = $this->memory->ReadBuffer($offset,4);
		return (ord($bin[0])) | (ord($bin[1]) <<  8) | (ord($bin[2]) << 16) | (ord($bin[3]) << 24);
	}

	public function WriteUInt8($offset, $data) {
		$this->memory->WriteBuffer($offset, chr($data));
	}
	public function WriteUInt16($offset, $data) {
		$this->memory->WriteBuffer($offset, chr($data) . chr($data >>  8));
	}
	public function WriteUInt32($offset, $data) {
		$this->memory->WriteBuffer($offset, chr($data) . chr($data >>  8) . chr($data >> 16) . chr($data >> 24) );
	}

	
	public function ReadInt8($offset) {
		$num = $this->ReadUInt8($offset);
		if ( $num & $this->_8 ) { $num |= ($this->_i8); }
		return $num;
	}
	public function ReadInt16($offset) {
		$num = $this->ReadUInt16($offset);
		if ( $num & $this->_16 ) { $num |= ($this->_i16); }
		return $num;
	}
	public function ReadInt32($offset) {
		$num = $this->ReadUInt32($offset);
		if ( $num & $this->_32 ) { $num |= ($this->_i32); }
		return $num;
	}
	
	public function WriteInt8($offset, $data) {
		if ( $data < 0 ) { $data |= $this->_8; }
		return $this->WriteUInt8($offset, $data);
	}
	public function WriteInt16($offset, $data) {
		if ( $data < 0 ) { $data |= $this->_16; }
		return $this->WriteUInt16($offset, $data);
	}
	public function WriteInt32($offset, $data) {
		if ( $data < 0 ) { $data |= $this->_32; }
		return $this->WriteUInt32($offset, $data);
	}

	
	public function ReadBuffer($offset, $size) {
		$this->memory->ReadBuffer($offset, $size);
	}
	public function WriteBuffer($offset, $data) {
		$this->memory->WriteBuffer($offset, $data);
	}
}
