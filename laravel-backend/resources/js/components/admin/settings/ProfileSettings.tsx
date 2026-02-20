import { ChangeEvent, FormEvent, useEffect, useRef, useState } from "react";
import { FaCamera } from "react-icons/fa";
import { profileIcon } from "../../../assets/images";
import { useAuthContextProvider } from "../../../hooks/hooks";
import axios from "../../api/axios";
import {
  AcademicRanksOptions,
  CollegeOption,
  UnitOptions,
} from "../../../constant/UserDropOptionsData";
import { useNavigate } from "react-router-dom";
import { User } from "../../shared/types/types";

const getImageUrl = (path: string | null | undefined) => {
  if (!path) return "https://via.placeholder.com/150"; 

  if (path.startsWith("http://localhost/storage")) {
      return path.replace("http://localhost/", "http://localhost:8000/");
  }

  if (path.startsWith("http")) return path; 

  return `http://localhost:8000/storage/${path}`; 
};
const initialData = {
  id: 0,
  fname: "",
  lname: "",
  mi: "",
  suffix: "",
  email: "",
  image_path: "",
  password: "",
  academic_rank: "",
  unit: "",
  college: "",
  created_at: null,
  deleted_at: null,
  updated_at: null,
  roles: [{ name: "" }] as [{ name: string }],
};

// type User = Omit<UserType, "roles" | "id" | "research_monitoring_form">;

export default function ProfileSettings() {
  const navigate = useNavigate();
  const { user, setUser } = useAuthContextProvider();
  const [profilePic, setProfilePic] = useState<string | undefined>(
    user?.image_path ? user.image_path : profileIcon,
  );
  const imgInput = useRef<HTMLInputElement | null>(null);
  const [formData, setFormData] = useState<User>(initialData);

  useEffect(() => {
    if (!user) return;

    try {
      setFormData({
        fname: user.fname,
        lname: user.lname,
        mi: user.mi,
        email: user.email,
        image_path: user.image_path,
        academic_rank: user.academic_rank,
        unit: user.unit,
        suffix: user.suffix,
        id: user.id,
        college: user.college,
        created_at: user.created_at,
        deleted_at: user.deleted_at,
        updated_at: user.updated_at,
        roles: user.roles || [{ id: 1, name: "" }],
      });
    } catch (error) {
      console.error("Error setting form data:", error);
    }
  }, [user]);

  const uploadProfile = async (e: ChangeEvent<HTMLInputElement>) => {
    const imageData = new FormData();
    const { files } = e.target;

    setProfilePic(files ? URL.createObjectURL(files[0]) : undefined);

    if (!files || !files[0]) return;
    imageData.append("image_path", files[0]);

    try {
      const response = await axios.post("/api/file-upload-public", imageData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });

      const imagePath =
        response.data.image_path || response.data?.data?.image_path;

      setFormData({
        ...formData,
        image_path: imagePath,
      });
    } catch (error) {
      console.error("File upload failed: ", error);
    }
  };

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    try {
      const response = await axios.put(
        `api/coordinators/${formData.id}`,
        formData,
      );

      setUser(response.data.data);
      sessionStorage.setItem("User", JSON.stringify(response.data.data));
      navigate("/admin-settings/profile");
    } catch (error) {
      console.error(error);
    }
  };

  return (
    <section>
      <form
        onSubmit={handleSubmit}
        className="space-y-10 rounded-md bg-white p-10 shadow-custom"
      >
        <h1 className="text-2xl font-bold text-gray-700">Profile Details</h1>

        {/* =======================================! Profile Picture !======================================= */}
        <div className="relative h-24 w-24 cursor-pointer">
          <div
            onClick={() => imgInput.current?.click()}
            className="over relative h-full w-full overflow-hidden rounded-full border-2 border-gray-400 bg-white"
          >
            <img
              // src={profilePic}
              src={getImageUrl(formData.image_path)}

              alt="Profile Picture"
              width={200}
              height={200}
              className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2"
            />
            <input
              type="file"
              accept="image/*"
              ref={imgInput}
              onChange={uploadProfile}
              className="hidden"
            />
          </div>
          <div
            onClick={() => imgInput.current?.click()}
            className="absolute bottom-0 right-0 grid h-8 w-8 translate-x-1 place-content-center rounded-full border border-gray-400 bg-[#e2e5e9]"
          >
            <FaCamera />
          </div>
        </div>
        {/* =======================================! First Name !======================================= */}
        <div>
          <label htmlFor="fname" className="mb-2 block font-bold text-gray-700">
            First Name
          </label>
          <input
            type="text"
            id="fname"
            name="fname"
            value={formData.fname}
            onChange={handleChange}
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {/* =======================================! Middle Initial !======================================= */}
        <div>
          <label htmlFor="mi" className="mb-2 block font-bold text-gray-700">
            Middle Initial
          </label>
          <input
            type="text"
            id="mi"
            name="mi"
            value={formData.mi}
            onChange={handleChange}
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {/* =======================================! Last Name !======================================= */}
        <div>
          <label htmlFor="lname" className="mb-2 block font-bold text-gray-700">
            Last Name
          </label>
          <input
            type="text"
            id="lname"
            name="lname"
            value={formData.lname}
            onChange={handleChange}
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {/* =======================================! Suffix !======================================= */}
        <div>
          <label
            htmlFor="suffix"
            className="mb-2 block font-bold text-gray-700"
          >
            Suffix
          </label>
          <input
            type="text"
            id="suffix"
            name="suffix"
            value={formData.suffix}
            onChange={handleChange}
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {/* =======================================! Academic Rank !======================================= */}
        <div>
          <label
            htmlFor="academickRank"
            className="mb-2 block font-bold text-gray-700"
          >
            Academic Rank
          </label>
          <select
            name="academic_rank"
            id="academickRank"
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          >
            <option defaultValue={formData.academic_rank}>
              {formData.academic_rank}
            </option>
            {AcademicRanksOptions.map((rank) => (
              <option key={rank} value={rank}>
                {rank}
              </option>
            ))}
          </select>
        </div>
        {/* =======================================! Unit !======================================= */}
        <div>
          <label htmlFor="unit" className="mb-2 block font-bold text-gray-700">
            Unit
          </label>
          <select
            name="academic_rank"
            id="unit"
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          >
            <option defaultValue={formData.unit}>{formData.unit}</option>
            {UnitOptions.map((unit) => (
              <option key={unit} value={unit}>
                {unit}
              </option>
            ))}
          </select>
        </div>
        {/* =======================================! College !======================================= */}
        <div>
          <label
            htmlFor="college"
            className="mb-2 block font-bold text-gray-700"
          >
            College
          </label>
          <select
            name="academic_rank"
            id="college"
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          >
            <option defaultValue={formData.college}>{formData.college}</option>
            {CollegeOption.map((college) => (
              <option key={college} value={college}>
                {college}
              </option>
            ))}
          </select>
        </div>
        {/* =======================================! Email !======================================= */}
        <div>
          <label htmlFor="email" className="mb-2 block font-bold text-gray-700">
            Email
          </label>
          <input
            type="text"
            id="email"
            name="email"
            value={formData.email}
            onChange={handleChange}
            className="w-full rounded-md border-2 border-gray-300 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {/* =======================================! Buttons !======================================= */}
        <div className="space-x-10">
          <button
            formAction="submit"
            className="rounded-md border-2 border-blue-700 bg-blue-700 px-3 py-2 font-semibold text-white hover:border-blue-500 hover:bg-blue-500"
          >
            Update
          </button>
          <button className="border- rounded-md border-2 border-blue-700 px-3 py-2 font-semibold text-gray-700 hover:border-blue-500 hover:bg-blue-500 hover:text-white">
            Cancel
          </button>
        </div>
      </form>
    </section>
  );
}
