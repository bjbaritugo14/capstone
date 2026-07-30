import { Report, ReportPayload, User, VehicularAccident, VehicularAccidentPayload } from '../types';

const sleep = (ms = 350) => new Promise((resolve) => setTimeout(resolve, ms));

const mockUser: User = {
  id: 1,
  name: 'Demo Responder',
  email: 'demo@example.com',
  role: 'field_officer',
};

let mockReports: Report[] = [
  {
    id: 1,
    barangay: 'Poblacion',
    purok: 'Purok 3',
    description: 'Strong winds damaged several roofs and scattered debris in the area.',
    disasterType: 'Typhoon',
    severity: 'severe',
    families: [
      { familyHeadName: 'Juan Dela Cruz', householdMembers: 5, contactNumber: '09171234567', evacuationStatus: 'Evacuated', description: 'House partially collapsed', severity: 'severe', latitude: '6.6798', longitude: '125.2321', photos: [] },
      { familyHeadName: 'Maria Santos', householdMembers: 3, contactNumber: '09181234567', evacuationStatus: 'In Evacuation Center', description: 'Roof blown off', severity: 'moderate', latitude: '6.6800', longitude: '125.2325', photos: [] },
    ],
    affectedStructures: 8,
    photos: [],
    longitude: '125.2321',
    latitude: '6.6798',
    reportDate: '2026-03-22',
    status: 'validated',
    validationRemarks: 'Validated by MDRRMO.',
    validatedAt: new Date().toISOString(),
    validatedBy: 'MDRRMO Validator',
    createdAt: new Date().toISOString(),
  },
  {
    id: 2,
    barangay: 'Saub',
    purok: 'Purok 1',
    description: 'Flood water entered homes and made road access difficult.',
    disasterType: 'Flood',
    severity: 'moderate',
    families: [
      { familyHeadName: 'Pedro Reyes', householdMembers: 7, contactNumber: '09191234567', evacuationStatus: 'Not Evacuated', description: 'Ground floor flooded', severity: 'moderate', latitude: '6.6900', longitude: '125.2200', photos: [] },
      { familyHeadName: 'Ana Garcia', householdMembers: 4, contactNumber: '09201234567', evacuationStatus: 'Evacuated', description: 'Furniture damaged by water', severity: 'minor', latitude: '6.6902', longitude: '125.2202', photos: [] },
      { familyHeadName: 'Roberto Lim', householdMembers: 6, contactNumber: '09211234567', evacuationStatus: 'Returned Home', description: 'Minor water damage', severity: 'minor', latitude: '6.6905', longitude: '125.2205', photos: [] },
    ],
    affectedStructures: 14,
    photos: [],
    longitude: '125.2200',
    latitude: '6.6900',
    reportDate: '2026-03-21',
    status: 'returned',
    validationRemarks: 'Please update the family damage details and capture clearer GPS coordinates.',
    validatedAt: new Date().toISOString(),
    validatedBy: 'MDRRMO Validator',
    createdAt: new Date().toISOString(),
  },
];

let mockVehicularAccidents: VehicularAccident[] = [
  {
    id: 1,
    barangay: 'Poblacion',
    purok: 'Purok 2',
    roadSegment: 'National Highway',
    personName: 'Juan Dela Cruz',
    accidentType: 'Motorcycle collision',
    vehicleType: 'Motorcycle',
    description: 'Two motorcycles collided near the municipal road.',
    vehiclesInvolved: 2,
    injuredCount: 1,
    fatalityCount: 0,
    involvedPersons: [
      { personName: 'Juan Dela Cruz', role: 'Driver', contactNumber: '09171234567' },
    ],
    photos: [],
    longitude: '125.232100',
    latitude: '6.679800',
    incidentDate: '2026-05-06',
    status: 'recorded',
    validationRemarks: '',
    validatedAt: '',
    validatedBy: '',
    createdAt: new Date().toISOString(),
  },
];

export const mockApi = {
  async login(email: string, password: string) {
    await sleep();

    if (!email || !password) {
      throw new Error('Email and password are required.');
    }

    return {
      token: 'mock-token-123',
      user: {
        ...mockUser,
        email,
      },
    };
  },

  async getReports() {
    await sleep(200);
    return [...mockReports].sort((a, b) => b.id - a.id);
  },

  async createReport(payload: ReportPayload) {
    await sleep();
    const newReport: Report = {
      ...payload,
      id: Date.now(),
      status: 'pending',
      validationRemarks: '',
      validatedAt: '',
      validatedBy: '',
      createdAt: new Date().toISOString(),
    };
    mockReports = [newReport, ...mockReports];
    return newReport;
  },

  async updateReport(id: number, payload: ReportPayload) {
    await sleep();
    mockReports = mockReports.map((report) => {
      if (report.id !== id) {
        return report;
      }

      return {
        ...report,
        ...payload,
        status: report.status === 'returned' ? 'pending' : report.status,
      };
    });

    const updated = mockReports.find((report) => report.id === id);
    if (!updated) throw new Error('Report not found.');
    return updated;
  },

  async deleteReport(id: number) {
    await sleep(200);
    mockReports = mockReports.filter((report) => report.id !== id);
    return true;
  },

  async updatePassword(currentPassword: string, newPassword: string) {
    await sleep();
    if (!currentPassword || !newPassword) {
      throw new Error('Current and new password are required.');
    }
    return { success: true };
  },

  async getVehicularAccidents() {
    await sleep(200);
    return [...mockVehicularAccidents].sort((a, b) => b.id - a.id);
  },

  async createVehicularAccident(payload: VehicularAccidentPayload) {
    await sleep();
    const newAccident: VehicularAccident = {
      ...payload,
      id: Date.now(),
      status: 'recorded',
      validationRemarks: '',
      validatedAt: '',
      validatedBy: '',
      createdAt: new Date().toISOString(),
    };
    mockVehicularAccidents = [newAccident, ...mockVehicularAccidents];
    return newAccident;
  },

  async updateVehicularAccident(id: number, payload: VehicularAccidentPayload) {
    await sleep();
    mockVehicularAccidents = mockVehicularAccidents.map((accident) => {
      if (accident.id !== id) {
        return accident;
      }

      return {
        ...accident,
        ...payload,
        status: accident.status === 'returned' ? 'recorded' : accident.status,
      };
    });

    const updated = mockVehicularAccidents.find((accident) => accident.id === id);
    if (!updated) throw new Error('Accident report not found.');
    return updated;
  },

  async deleteVehicularAccident(id: number) {
    await sleep(200);
    mockVehicularAccidents = mockVehicularAccidents.filter((accident) => accident.id !== id);
    return true;
  },
};
