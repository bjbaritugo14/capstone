export type User = {
  id: number;
  name: string;
  email: string;
};

export type DisasterType = 'Flood' | 'Typhoon' | 'Landslide' | 'Earthquake' | 'Fire' | 'Other';
export type Severity = 'minor' | 'moderate' | 'severe';

export type AffectedFamily = {
  familyHeadName: string;
  householdMembers: number;
  contactNumber: string;
  evacuationStatus: string;
  description: string;
  severity: Severity;
  latitude: string;
  longitude: string;
  photos: string[];
};

export type Report = {
  id: number;
  barangay: string;
  purok: string;
  description: string;
  disasterType: DisasterType;
  severity: Severity;
  families: AffectedFamily[];
  affectedStructures: number;
  photos: string[];
  longitude: string;
  latitude: string;
  reportDate: string;
  createdAt: string;
};

export type ReportPayload = Omit<Report, 'id' | 'createdAt'>;

export const DISASTER_TYPES: DisasterType[] = ['Flood', 'Typhoon', 'Landslide', 'Earthquake', 'Fire', 'Other'];
export const SEVERITY_LEVELS: Severity[] = ['minor', 'moderate', 'severe'];
export const MATANAO_BARANGAYS = [
  'Asbang',
  'Asinan',
  'Bagumbayan',
  'Bangkal',
  'Buas',
  'Buri',
  'Cabligan',
  'Camanchiles',
  'Ceboza',
  'Colonsabak',
  'Dongan-Pekong',
  'Kabasagan',
  'Kapok',
  'Kauswagan',
  'Kibao',
  'La Suerte',
  'Langa-an',
  'Lower Marber',
  'Manga',
  'New Katipunan',
  'New Murcia',
  'New Visayas',
  'Poblacion',
  'Saboy',
  'San Jose',
  'San Miguel',
  'San Vicente',
  'Saub',
  'Sinaragan',
  'Sinawilan',
  'Tamlangon',
  'Tibongbong',
  'Towak',
] as const;

export type AccidentStatus = 'recorded' | 'verified' | 'closed';

export type InvolvedPerson = {
  personName: string;
  role: string;
  contactNumber: string;
};

export type VehicularAccident = {
  id: number;
  barangay: string;
  purok: string;
  roadSegment: string;
  personName: string;
  accidentType: string;
  vehicleType: string;
  description: string;
  vehiclesInvolved: number;
  injuredCount: number;
  fatalityCount: number;
  involvedPersons: InvolvedPerson[];
  photos: string[];
  longitude: string;
  latitude: string;
  incidentDate: string;
  status: AccidentStatus;
  createdAt: string;
};

export type VehicularAccidentPayload = Omit<VehicularAccident, 'id' | 'status' | 'createdAt'>;
