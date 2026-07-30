export type UserRole = 'super_admin' | 'admin' | 'mdrrmo' | 'validator' | 'dswd' | 'field_officer';

export type User = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
};

export type BarangayOption = {
  id: number;
  name: string;
  municipality: string;
  province: string;
};

export type DisasterType = 'Flood' | 'Typhoon' | 'Landslide' | 'Earthquake' | 'Fire' | 'Other';
export type Severity = 'minor' | 'moderate' | 'severe';
export type ReportStatus = 'pending' | 'validated' | 'returned' | 'rejected';

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
  status: ReportStatus;
  validationRemarks: string;
  validatedAt: string;
  validatedBy: string;
  createdAt: string;
};

export type ReportPayload = Omit<Report, 'id' | 'status' | 'validationRemarks' | 'validatedAt' | 'validatedBy' | 'createdAt'>;

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

export type AccidentStatus = 'recorded' | 'validated' | 'returned' | 'verified' | 'closed';

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
  validationRemarks: string;
  validatedAt: string;
  validatedBy: string;
  createdAt: string;
};

export type VehicularAccidentPayload = Omit<VehicularAccident, 'id' | 'status' | 'validationRemarks' | 'validatedAt' | 'validatedBy' | 'createdAt'>;
